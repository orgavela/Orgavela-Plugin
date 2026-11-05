<?php
/**
 * Plugin Name: UOP Core
 * Description: Foundation for organization-owned participant operations.
 * Version: 0.1.0-alpha.2
 * Requires at least: 6.9
 * Requires PHP: 8.3
 * Text Domain: uop-core
 * License: GPL-2.0-or-later
 *
 * @package UOP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Keep this entrypoint parseable on older PHP; load modern code only after the gate.
if ( version_compare( PHP_VERSION, '8.3', '<' ) ) {
	register_activation_hook(
		__FILE__,
		static function () {
			wp_die( esc_html__( 'UOP Core requires PHP 8.3 or newer.', 'uop-core' ) );
		}
	);
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'UOP Core requires PHP 8.3 or newer.', 'uop-core' ) . '</p></div>';
		}
	);
	return;
}

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'UOP Core dependencies are missing. Install the complete release package.', 'uop-core' ) . '</p></div>';
		}
	);
	register_activation_hook(
		__FILE__,
		static function () {
			wp_die( esc_html__( 'UOP Core dependencies are missing.', 'uop-core' ) );
		}
	);
	return;
}

define( 'UOP_CORE_VERSION', '0.1.0-alpha.2' );
define( 'UOP_CORE_FILE', __FILE__ );
require_once __DIR__ . '/vendor/autoload.php';

register_activation_hook( __FILE__, array( \UOP\Core\Bootstrap::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \UOP\Core\Bootstrap::class, 'deactivate' ) );
add_action( 'plugins_loaded', array( \UOP\Core\Bootstrap::class, 'boot' ), -20 );
