<?php
/**
 * Resumable migration step execution.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use Closure;
use UOP\Core\TransactionManager;
/** DDL is never presented as transactionally reversible. */
final class MigrationContext {
	/**
	 * Bind the current migration.
	 *
	 * @param MigrationState     $state Durable checkpoint.
	 * @param TransactionManager $transactions Transactional DML steps.
	 * @param int                $version Current migration version.
	 */
	public function __construct( private MigrationState $state, private TransactionManager $transactions, private int $version ) {}

	/**
	 * Run or verify a completed idempotent step.
	 *
	 * @param string  $key Stable step identifier.
	 * @param Closure $apply Idempotent work.
	 * @param Closure $verify Verification.
	 * @param bool    $transactional Whether work is rollback-capable DML.
	 * @phpstan-param Closure(): void $apply
	 * @phpstan-param Closure(): void $verify
	 */
	public function step( string $key, Closure $apply, Closure $verify, bool $transactional = false ): void {
		/**
		 * Verified step keys for this version.
		 *
		 * @var array<string, bool> $completed
		 */
		$completed = $this->state->read( 'uop_migration_progress_' . $this->version, array() );
		if ( isset( $completed[ $key ] ) ) {
			$verify();
			return;
		}
		$work = function () use ( $apply, $verify, $key, $completed ): void {
			$apply();
			$verify();
			$completed[ $key ] = true;
			$this->state->write( 'uop_migration_progress_' . $this->version, $completed );
		};
		if ( $transactional ) {
			$this->transactions->run( $work );
		} else {
			$work();
		}
	}
}
