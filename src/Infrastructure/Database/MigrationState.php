<?php
/**
 * Durable migration checkpoints in WordPress options.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

/** Reads state from the database under the runner lock, never from a stale option cache. */
final class MigrationState {
	/**
	 * Internal coordination state.
	 *
	 * @var bool Whether owned options bypass the shared cache.
	 */
	private static bool $reads_registered = false;
	/**
	 * Internal coordination state.
	 *
	 * @var int Site owning this options table.
	 */
	private int $blog_id;
	/**
	 * Bind site options.
	 *
	 * @param Connection $db Connection.
	 * @param string     $options Site options table.
	 */
	public function __construct( private Connection $db, private string $options ) {
		$this->blog_id = get_current_blog_id();
		self::register_option_reads();
	}

	/** Prevent stale late cache fills and publication of uncommitted option values. */
	public static function register_option_reads(): void {
		if ( self::$reads_registered ) {
			return;
		}
		self::$reads_registered = true;
		add_filter(
			'pre_option',
			static function ( mixed $pre, string $key, mixed $fallback ): mixed {
				if ( ! preg_match( '/^uop_(?:db_version|data_version|default_organization_id|migration_status|migration_progress_[0-9]+)$/D', $key ) ) {
					return $pre;
				}
				global $wpdb;
				$state = new self( new WpdbConnection( $wpdb ), $wpdb->options );
				$value = $state->read( $key, $fallback );
				if ( false === $value ) {
					// WordPress uses false as the no-short-circuit sentinel. Clear any
					// stale positive entry before its normal missing-option lookup.
					$state->invalidate( $key );
				}
				return $value;
			},
			PHP_INT_MAX,
			3
		);
	}

	/**
	 * Read durable state.
	 *
	 * @param string $key Internal option name.
	 * @param mixed  $fallback Default.
	 * @return mixed
	 */
	public function read( string $key, mixed $fallback = null ): mixed {
		$rows = $this->db->rows( 'SELECT option_value FROM %i WHERE option_name = %s', array( $this->options, $key ) );
		return $rows ? maybe_unserialize( $rows[0]['option_value'] ) : $fallback;
	}

	/**
	 * Persist state on the same database connection as the migration.
	 *
	 * @param string $key Internal option name.
	 * @param mixed  $value State value.
	 */
	public function write( string $key, mixed $value ): void {
		$this->db->execute( "INSERT INTO %i (option_name, option_value, autoload) VALUES (%s, %s, 'off') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = 'off'", array( $this->options, $key, (string) maybe_serialize( $value ) ) );
		TransactionState::for_connection( $this->db )->after_completion( fn () => $this->invalidate( $key ) );
	}

	/**
	 * Invalidate the owning site's cache on commit, rollback or autocommit.
	 *
	 * @param string $key Owned option name.
	 */
	private function invalidate( string $key ): void {
		$switched = get_current_blog_id() !== $this->blog_id;
		if ( $switched ) {
			switch_to_blog( $this->blog_id );
		}
		try {
			wp_cache_delete( $key, 'options' );
			wp_cache_delete( 'alloptions', 'options' );
			wp_cache_delete( 'notoptions', 'options' );
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}
}
