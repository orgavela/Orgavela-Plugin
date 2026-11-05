<?php
/**
 * Prepared WordPress database adapter.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use wpdb;
/** No cache: transactions, migration locks and scoped reads require current data. */
final class WpdbConnection implements Connection {
	/**
	 * Original session identity; reconnects invalidate transactions and locks.
	 *
	 * @var int
	 */
	private int $session_id;
	/**
	 * Internal coordination state.
	 *
	 * @var object Stable physical connection identity.
	 */
	private object $identity;
	/**
	 * Prevent recursive fencing of the lock ownership query.
	 *
	 * @var bool
	 */
	private bool $checking_schema = false;
	/**
	 * Bind a WordPress connection.
	 *
	 * @param wpdb $wpdb WordPress database.
	 */
	public function __construct( private wpdb $wpdb ) {
		$handle           = $wpdb->__get( 'dbh' );
		$this->session_id = $handle instanceof \mysqli ? $handle->thread_id : 0;
		$this->identity   = $handle instanceof \mysqli ? $handle : $wpdb;
	}

	/**
	 * Share transaction ownership across adapter instances.
	 *
	 * @return object
	 */
	public function identity(): object {
		return $this->identity;
	}

	/**
	 * Fence every dbDelta query on the original session and lock owner.
	 *
	 * @param string $ddl Frozen, validated CREATE TABLE definition.
	 * @param string $lock_name Required advisory lock.
	 * @throws DatabaseException On lock loss or SQL failure.
	 */
	public function apply_schema( string $ddl, string $lock_name ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$driver  = new \mysqli_driver(); // phpcs:ignore WordPress.DB.RestrictedFunctions -- Inspect and restore driver error mode; queries still use wpdb.
		$mode    = $driver->report_mode;
		$retries = $this->wpdb->__get( 'reconnect_retries' );
		$prior   = $this->wpdb->suppress_errors( true );
		$fence   = function ( string $query ) use ( $lock_name ): string {
			if ( ! $this->checking_schema ) {
				$this->checking_schema = true;
				try {
					$owner = $this->rows( 'SELECT IS_USED_LOCK(%s) AS owner', array( $lock_name ) );
					if ( (int) ( $owner[0]['owner'] ?? 0 ) !== $this->session_id ) {
						throw new DatabaseException( 'Migration lock lost.' );
					}
				} finally {
					$this->checking_schema = false;
				}
			}
			return $query;
		};
		$this->wpdb->__set( 'reconnect_retries', 0 );
		// Strict driver errors abort before wpdb can reconnect or terminate the request.
		mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT ); // phpcs:ignore WordPress.DB.RestrictedFunctions -- Preserve the locked physical session during WordPress's dbDelta queries.
		add_filter( 'query', $fence, PHP_INT_MAX );
		try {
			try {
				dbDelta( $ddl );
			} catch ( \mysqli_sql_exception $error ) {
				// dbDelta probes a missing table with DESCRIBE. Only this expected error
				// permits the original CREATE; it still passes through the lock fence.
				preg_match( '/^CREATE TABLE ([a-zA-Z0-9_]+) /', $ddl, $table );
				if ( 1146 !== $error->getCode() || ! isset( $table[1] ) || 'DESCRIBE ' . $table[1] . ';' !== $this->wpdb->last_query ) {
					throw new DatabaseException( 'Schema operation failed.', $error->getCode() );
				}
				$this->execute( $ddl );
			}
		} catch ( \mysqli_sql_exception $error ) {
			throw new DatabaseException( 'Schema operation failed.', (int) $error->getCode() );
		} finally {
			remove_filter( 'query', $fence, PHP_INT_MAX );
			mysqli_report( $mode ); // phpcs:ignore WordPress.DB.RestrictedFunctions -- Restore caller driver mode.
			$this->wpdb->__set( 'reconnect_retries', $retries );
			$this->wpdb->suppress_errors( $prior );
		}
	}

	/**
	 * Execute prepared SQL without leaking database diagnostics.
	 *
	 * @param string $sql Infrastructure SQL.
	 * @param array  $args Bound values and identifiers.
	 * @phpstan-param list<int|float|string> $args
	 * @return int
	 * @throws DatabaseException If execution fails.
	 */
	public function execute( string $sql, array $args = array() ): int {
		$query     = $this->prepare( $sql, $args );
		$prior     = $this->wpdb->suppress_errors( true );
		$retries   = $this->wpdb->__get( 'reconnect_retries' );
		$reporting = error_reporting(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions,WordPress.PHP.DiscouragedPHPFunctions -- Read caller configuration to restore after WordPress's connection check.
		$this->wpdb->__set( 'reconnect_retries', 0 );
		try {
			$handle = $this->wpdb->__get( 'dbh' );
			if ( ! $handle instanceof \mysqli || 0 === $this->session_id || $handle->thread_id !== $this->session_id ) {
				throw new DatabaseException( 'Database connection changed.', 2006 );
			}
			// Reconnecting would lose transaction and advisory-lock ownership. Fail closed.
			if ( ! $this->wpdb->check_connection( false ) ) {
				throw new DatabaseException( 'Database connection lost.', 2006 );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Prepared by the single adapter method below; uncached infrastructure query.
			$result = $this->wpdb->query( $query );
			if ( false === $result ) {
				$handle = $this->wpdb->__get( 'dbh' );
				$errno  = $handle instanceof \mysqli ? $handle->errno : 0;
				throw new DatabaseException( 'Database operation failed.', $errno );
			}
			return (int) $result;
		} finally {
			// WordPress check_connection can lower reporting when WP_DEBUG is on; restore caller state.
			error_reporting( $reporting ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions,WordPress.PHP.DiscouragedPHPFunctions -- Restores the previous value, never disables diagnostics.
			$this->wpdb->__set( 'reconnect_retries', $retries );
			$this->wpdb->suppress_errors( $prior );
		}
	}

	/**
	 * Read rows using the same error boundary.
	 *
	 * @param string $sql Infrastructure SQL.
	 * @param array  $args Bound values and identifiers.
	 * @phpstan-param list<int|float|string> $args
	 * @return list<array<string, mixed>>
	 */
	public function rows( string $sql, array $args = array() ): array {
		$this->execute( $sql, $args );
		return array_map( static fn ( object $row ): array => (array) $row, $this->wpdb->last_result );
	}

	/**
	 * Centralized placeholder expansion.
	 *
	 * @param string $sql Infrastructure SQL only.
	 * @param array  $args Bound values.
	 * @phpstan-param list<int|float|string> $args
	 * @return string
	 */
	private function prepare( string $sql, array $args ): string {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL templates are private infrastructure contracts; all variable values are arguments.
		return $args ? (string) $this->wpdb->prepare( $sql, $args ) : $sql;
	}
}
