<?php
/**
 * Ordered versioned migration contract.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

/** Each migration expands safely and verifies its own invariants. */
interface Migration {
	/**
	 * Get structural version.
	 *
	 * @return int
	 */
	public function version(): int;
	/**
	 * Apply idempotent steps through the checkpoint context.
	 *
	 * @param MigrationContext $context Execution context.
	 */
	public function up( MigrationContext $context ): void;
	/**
	 * Verify before advancing installed version.
	 *
	 * @param MigrationContext $context Execution context.
	 */
	public function verify( MigrationContext $context ): void;
}
