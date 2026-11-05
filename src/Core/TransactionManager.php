<?php
/**
 * Complete transaction retry boundary.
 *
 * @package UOP
 */

namespace UOP\Core;

use Closure;
use LogicException;
use Throwable;
use UOP\Infrastructure\Database\Connection;
use UOP\Infrastructure\Database\DatabaseException;
use UOP\Infrastructure\Database\TransactionState;
/** Nested business transactions are explicitly forbidden. */
final class TransactionManager {
	/**
	 * Active transaction flag.
	 *
	 * @var bool
	 */
	private bool $active = false;
	/**
	 * Deferred callbacks for the current attempt only.
	 *
	 * @var list<Closure(): void>
	 */
	private array $callbacks = array();

	/**
	 * Explicit retry and post-commit diagnostic seams.
	 *
	 * @param Connection $connection Database.
	 * @param Closure    $pause Jitter delay in microseconds.
	 * @param Closure    $diagnose Post-commit error handler.
	 * @phpstan-param Closure(int): void $pause
	 * @phpstan-param Closure(Throwable): void $diagnose
	 */
	public function __construct( private Connection $connection, private Closure $pause, private Closure $diagnose ) {}

	/**
	 * Execute once plus at most three complete retries after deadlock.
	 *
	 * @template T
	 * @param Closure $work Transactional work, without external side effects.
	 * @phpstan-param Closure(): T $work
	 * @return mixed
	 * @phpstan-return T
	 * @throws LogicException On nested transaction use.
	 * @throws Throwable On business or database failure.
	 */
	public function run( Closure $work ): mixed {
		$state = TransactionState::for_connection( $this->connection );
		if ( $state->active ) {
			throw new LogicException( 'Nested business transactions are forbidden.' );
		}
		for ( $attempt = 0; ; ++$attempt ) {
			$state->active   = true;
			$this->active    = true;
			$this->callbacks = array();
			try {
				$this->connection->execute( 'START TRANSACTION' );
				$result = $work();
				$this->connection->execute( 'COMMIT' );
			} catch ( Throwable $error ) {
				$this->callbacks = array();
				try {
					$this->connection->execute( 'ROLLBACK' );
				} finally {
					$this->active  = false;
					$state->active = false;
					$this->complete_attempt( $state );
				}
				if ( $error instanceof DatabaseException && 1213 === $error->getCode() && $attempt < 3 ) {
					( $this->pause )( random_int( 1000, 10000 ) );
					continue;
				}
				throw $error;
			}
			$this->active  = false;
			$state->active = false;
			$this->complete_attempt( $state );
			$callbacks       = $this->callbacks;
			$this->callbacks = array();
			foreach ( $callbacks as $callback ) {
				try {
					$callback();
				} catch ( Throwable $error ) {
					try {
						( $this->diagnose )( $error );
					} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Diagnostics must not change successful command semantics.
						// Diagnostics cannot invalidate an already committed command.
					}
				}
			}
			return $result;
		}
	}

	/**
	 * Invalidate transactional caches on either outcome, before public callbacks.
	 *
	 * @param TransactionState $state Shared connection state.
	 */
	private function complete_attempt( TransactionState $state ): void {
		foreach ( $state->take_completion() as $callback ) {
			try {
				$callback();
			} catch ( Throwable $error ) {
				try {
					( $this->diagnose )( $error );
				} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Preserve the commit/rollback outcome even if cache diagnostics fail.
					// Owned migration options also bypass cache on reads.
				}
			}
		}
	}

	/**
	 * Defer an infrastructure callback until successful commit.
	 *
	 * @param Closure $callback Callback.
	 * @phpstan-param Closure(): void $callback
	 * @throws LogicException If called outside a transaction.
	 */
	public function after_commit( Closure $callback ): void {
		if ( ! $this->active ) {
			throw new LogicException( 'Post-commit callbacks require an active transaction.' );
		}
		$this->callbacks[] = $callback;
	}
}
