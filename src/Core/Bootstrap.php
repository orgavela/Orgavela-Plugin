<?php
/**
 * WordPress lifecycle adapter and composition root.
 *
 * @package UOP
 */

namespace UOP\Core;

use UOP\Extension\ModuleRegistry;
use UOP\Infrastructure\Database\Installer;
use UOP\Infrastructure\Database\MigrationState;

/** Runs no domain construction at global scope. */
final class Bootstrap {
	/**
	 * Internal state.
	 *
	 * @var Kernel|null Booted kernel.
	 */
	private static ?Kernel $kernel = null;

	/**
	 * Capture current compatibility failures.
	 *
	 * @return list<string>
	 */
	public static function errors(): array {
		global $wpdb, $wp_version;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Live read-only environment checks must not use stale cache.
		$engine         = $wpdb->get_var( "SELECT SUPPORT FROM information_schema.ENGINES WHERE ENGINE = 'InnoDB'" );
		$utf8           = $wpdb->get_var( "SELECT CHARACTER_SET_NAME FROM information_schema.CHARACTER_SETS WHERE CHARACTER_SET_NAME = 'utf8mb4'" );
		$db             = $wpdb->get_var( 'SELECT VERSION()' );
		$options_engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $wpdb->options ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
		return ( new EnvironmentChecker() )->errors( PHP_VERSION, $wp_version, (string) $db, in_array( $engine, array( 'YES', 'DEFAULT' ), true ) && 'InnoDB' === $options_engine, 'utf8mb4' === $utf8 && 'utf8mb4' === $wpdb->charset );
	}

	/** Validate before activation. */
	public static function activate(): void {
		MigrationState::register_option_reads();
		$errors = self::errors();
		if ( $errors ) {
			wp_die( esc_html( self::message( $errors ) ) );
		}
		try {
			Installer::runner()->run();
		} catch ( \Throwable ) {
			wp_die( esc_html__( 'UOP Core migration failed. Check the environment and migration state in Site Health before retrying.', 'uop-core' ) );
		}
		self::boot();
	}

	/** Preserve all data on deactivation. */
	public static function deactivate(): void {
		self::$kernel = null;
	}

	/** Boot only on a supported environment. */
	public static function boot(): void {
		MigrationState::register_option_reads();
		if ( null !== self::$kernel ) {
			return;
		}
		add_filter(
			'site_status_tests',
			static function ( array $tests ): array {
				$tests['direct']['uop_schema'] = array(
					'label' => 'UOP Core',
					'test'  => array( Installer::class, 'health' ),
				);
				return $tests;
			}
		);
		$errors = self::errors();
		if ( $errors ) {
			add_action(
				'admin_notices',
				static function () use ( $errors ): void {
					echo '<div class="notice notice-error"><p>' . esc_html( self::message( $errors ) ) . '</p></div>';
				}
			);
			return;
		}
		try {
			Installer::ensure_current();
		} catch ( \Throwable ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'UOP Core migration is incomplete. Check Site Health and retry after resolving the failure.', 'uop-core' ) . '</p></div>';
				}
			);
			return;
		}
		$container = new ServiceContainer();
		$modules   = new ModuleRegistry();
		$modules->add( new FoundationModule() );
		self::$kernel = new Kernel( $container, $modules );
		self::$kernel->boot();
	}

	/**
	 * Render an administrative diagnostic.
	 *
	 * @param array $errors Codes only.
	 * @phpstan-param list<string> $errors
	 * @return string
	 */
	private static function message( array $errors ): string {
		return __( 'UOP Core requires WordPress 6.9+, PHP 8.3+, MySQL 8.0+ or MariaDB 10.11+, InnoDB and utf8mb4. Failed checks: ', 'uop-core' ) . implode( ', ', $errors );
	}
}
