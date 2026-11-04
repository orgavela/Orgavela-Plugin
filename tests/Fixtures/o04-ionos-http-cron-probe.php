<?php
/**
 * Isolated test of the IONOS-style HTTP WordPress cron entrypoint.
 *
 * The script is CLI-only, synthetic and must never be bundled in the plugin.
 *
 * @package UOP
 */

use UOP\Infrastructure\Database\WpdbConnection;
use UOP\Infrastructure\Queue\GuardianQuotaScheduleHealth;

if ( 'cli' !== PHP_SAPI
	|| 'true' !== getenv( 'GITHUB_ACTIONS' )
	|| '1' !== getenv( 'UOP_TEST_ALLOW_DATABASE' )
	|| '1' !== getenv( 'UOP_O04_HTTP_CI' )
	|| '/tmp/uop-wordpress' !== getenv( 'WP_ROOT' )
	|| 'http://127.0.0.1:8080' !== home_url()
	|| ! defined( 'DISABLE_WP_CRON' )
	|| ! DISABLE_WP_CRON ) {
	throw new RuntimeException( 'Refusing O04 HTTP cron proof outside disposable GitHub WordPress CI.' );
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( ! is_plugin_active( 'uop-core/uop-core.php' )
	|| ! function_exists( 'as_schedule_single_action' )
	|| ! function_exists( 'as_get_scheduled_actions' ) ) {
	throw new RuntimeException( 'Active UOP Core and Action Scheduler are required for O04 HTTP proof.' );
}

global $wpdb;
$db         = new WpdbConnection( $wpdb );
$hook       = 'uop_guardian_quota_prune';
$group      = 'uop';
$cron       = 'action_scheduler_run_queue';
$context    = array( 'o04_disposable_http_only' );
$option_key = 'uop_ci_o04_http_action_id';
$quota_key  = 'uop_gclaim_q2_' . hash( 'sha256', 'uop_o04_http_cron_ci_subjectless_fixture' );
$mode       = (string) getenv( 'UOP_O04_HTTP_MODE' );
$now        = time();

$quota_exists = static function () use ( $db, $wpdb, $quota_key ): bool {
	return 1 === count(
		$db->rows(
			'SELECT option_value FROM %i WHERE option_name = %s',
			array( $wpdb->options, $quota_key )
		)
	);
};
$action_complete = static function ( int $action_id ) use ( $hook, $group ): bool {
	$ids = as_get_scheduled_actions(
		array(
			'hook'     => $hook,
			'group'    => $group,
			'status'   => 'complete',
			'per_page' => 100,
		),
		'ids'
	);
	return is_array( $ids ) && in_array( $action_id, array_map( 'intval', $ids ), true );
};

if ( 'seed' === $mode ) {
	if ( false !== get_option( $option_key, false ) || $quota_exists() ) {
		throw new RuntimeException( 'Disposable HTTP cron fixture already exists.' );
	}
	$db->execute(
		"INSERT INTO %i (option_name, option_value, autoload) VALUES (%s,%s,'off')",
		array( $wpdb->options, $quota_key, '3:' . ( $now - 3 * DAY_IN_SECONDS ) )
	);
	$action_id = as_schedule_single_action( $now - 600, $hook, array(), $group, false );
	if ( ! is_int( $action_id ) || $action_id < 1 ) {
		throw new RuntimeException( 'Could not seed due Guardian action.' );
	}
	if ( ! add_option( $option_key, $action_id, '', false ) ) {
		throw new RuntimeException( 'Could not save disposable action reference.' );
	}
	// The normal one-minute recurring WP-Cron event may still be in the
	// future; force a unique, due-once event with harmless synthetic args.
	// Its real Action Scheduler callback runs during the independent HTTP GET.
	if ( ! wp_schedule_single_event( $now - 120, $cron, $context, true ) ) {
		throw new RuntimeException( 'Could not schedule disposable due WordPress Action Scheduler trigger.' );
	}
	if ( ! $quota_exists()
		|| false === wp_next_scheduled( $cron, $context ) ) {
		throw new RuntimeException( 'Seeded HTTP-Cron event or option missing.' );
	}
	echo "PASS: disposable HTTP cron is due, WP-Cron auto-spawn disabled, expired synthetic quota and Guardian queue action still pending.\n";
	return;
}

if ( 'verify' === $mode ) {
	$action_id = (int) get_option( $option_key, 0 );
	if ( $action_id < 1 || ! $action_complete( $action_id ) ) {
		throw new RuntimeException( 'Direct HTTP wp-cron.php did not complete the real due Guardian Action Scheduler job.' );
	}
	if ( $quota_exists() ) {
		throw new RuntimeException( 'Direct HTTP cron did not remove the old synthetic Guardian quota.' );
	}
	if ( false !== wp_next_scheduled( $cron, $context ) ) {
		throw new RuntimeException( 'Direct HTTP cron failed to consume its one-off WP-Cron event.' );
	}
	if ( 'ok' !== GuardianQuotaScheduleHealth::inspect( time() ) ) {
		throw new RuntimeException( 'Guardian scheduler health is no longer healthy after the HTTP cron trigger.' );
	}
	delete_option( $option_key );
	echo "PASS: independent HTTP wp-cron.php executed the scheduled Guardian cleanup despite DISABLE_WP_CRON=true, removed expired synthetic quota and preserved healthy queue status.\n";
	return;
}

throw new RuntimeException( 'Unknown disposable O04 HTTP cron mode.' );
