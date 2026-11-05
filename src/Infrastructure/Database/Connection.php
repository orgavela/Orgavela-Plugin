<?php
/**
 * Database seam for transaction and migration testing.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

/** SQL is infrastructure-only, with prepared values and identifiers. */
interface Connection {
	/**
	 * Stable identity shared by wrappers of the same physical connection.
	 *
	 * @return object
	 */
	public function identity(): object;
	/**
	 * Apply dbDelta-compatible DDL while retaining the named migration lock.
	 *
	 * @param string $ddl Frozen schema SQL.
	 * @param string $lock_name Required connection-owned lock.
	 */
	public function apply_schema( string $ddl, string $lock_name ): void;
	/**
	 * Execute a statement.
	 *
	 * @param string $sql Prepared SQL template.
	 * @param array  $args Placeholder values.
	 * @phpstan-param list<int|float|string> $args
	 * @return int
	 */
	public function execute( string $sql, array $args = array() ): int;
	/**
	 * Read explicit columns.
	 *
	 * @param string $sql Prepared SQL template.
	 * @param array  $args Placeholder values.
	 * @phpstan-param list<int|float|string> $args
	 * @return list<array<string, mixed>>
	 */
	public function rows( string $sql, array $args = array() ): array;
}
