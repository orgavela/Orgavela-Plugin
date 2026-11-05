<?php
/**
 * Connection-wide transaction ownership and completion callbacks.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use Closure;
use WeakMap;

/** All managers and option writers on one physical connection share this state. */
final class TransactionState {
	/**
	 * Internal coordination state.
	 *
	 * @var WeakMap<object, self>|null Connection states.
	 */
	private static ?WeakMap $states = null;
	/**
	 * Internal coordination state.
	 *
	 * @var bool Whether a manager owns the connection.
	 */
	public bool $active = false;
	/**
	 * Internal coordination state.
	 *
	 * @var list<Closure(): void> Cache cleanup for either outcome of this attempt.
	 */
	private array $completion = array();

	/**
	 * Resolve shared state without retaining closed connection objects.
	 *
	 * @param Connection $db Connection identity.
	 * @return self
	 */
	public static function for_connection( Connection $db ): self {
		self::$states                           ??= new WeakMap();
		return self::$states[ $db->identity() ] ??= new self();
	}

	/**
	 * Defer cleanup until commit or rollback; autocommit writes clean immediately.
	 *
	 * @param Closure $callback Cleanup callback.
	 */
	public function after_completion( Closure $callback ): void {
		if ( $this->active ) {
			$this->completion[] = $callback;
		} else {
			$callback();
		}
	}

	/**
	 * Take this attempt's cleanup, including after a failed rollback.
	 *
	 * @return list<Closure(): void>
	 */
	public function take_completion(): array {
		$callbacks        = $this->completion;
		$this->completion = array();
		return $callbacks;
	}
}
