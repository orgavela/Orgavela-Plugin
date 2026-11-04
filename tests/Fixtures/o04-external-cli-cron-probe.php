<?php
/**
 * Independent WP-CLI Action Scheduler external-trigger proof.
 *
 * Never install this test fixture in a WordPress plugin release.
 * @package UOP
 */

use UOP\Infrastructure\Database\WpdbConnection;
use UOP\Infrastructure\Queue\GuardianQuotaScheduleHealth;

// Every invocation must use a fresh, disposable GitHub Actions site.
// This probe has no public endpoint or production activation path.
if ( 'cli' !== PHP_SAPI
	|| 'true' !== getenv( 'GITHUB_ACTIONS' )
	|| '1' !== getenv( 'UOP_TEST_ALLOW_DATABASE' )
	|| '1' !== getenv( 'UOP_O04_EXTERNAL_CI' )
	|| '/tmp/uop-wordpress' !== getenv( 'WP_ROOT' )
	|| 'http://127.0.0.1:8080' !== home_url()
	|| ! defined( 'DISABLE_WP_CRON' )
	|| ! DISABLE_WP_CRON ) {
	throw new RuntimeException( 'External cron test refused outside disposable CI.' );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( ! is_plugin_active( 'uop-core/uop-core.php' )
	|| ! function_exists( 'as_schedule_single_action' )
	|| ! function_exists( 'as_get_scheduled_actions' )
	|| ! function_exists( 'as_unschedule_action' ) ) {
	throw new RuntimeException( 'UOP Core and Action Scheduler must be active in CI.' );
}

global $wpdb;
$db        = new WpdbConnection( $wpdb );
$hook      = 'uop_guardian_quota_prune';
$group     = 'uop';
$other     = 'uop_ci_different_group';
$otherhook = 'uop_ci_unrelated_queue_task';
$key       = 'uop_gclaim_q2_' . hash( 'sha256', 'uop_external_cron_ci_subjectless_fixture' );
$ids_key   = 'uop_ci_o04_external_action_ids';
$mode      = (string) getenv( 'UOP_O04_EXTERNAL_MODE' );
$now       = time();

$action_status = static function ( string $expected_hook, string $expected_group, string $status, int $action_id ): bool {
	$ids = as_get_scheduled_actions(
		array(
			'hook'     => $expected_hook,
			'group'    => $expected_group,
			'status'   => $status,
			'per_page' => 100,
		),
		'ids'
	);
	return is_array( $ids ) && in_array( $action_id, array_map( 'intval', $ids ), true );
};
$quota_exists = static function () use ( $db, $wpdb, $key ): bool {
	$rows = $db->rows(
		'SELECT option_value FROM %i WHERE option_name = %s',
		array( $wpdb->options, $key )
	);
	return 1 === count( $rows );
};

if ( 'seed' === $mode ) {
	if ( 'ok' !== GuardianQuotaScheduleHealth::inspect( $now ) ) {
		throw new RuntimeException( 'Baseline scheduled and previously executed Guardian retention job not healthy.' );
	}
	if ( false !== get_option( $ids_key, false ) || $quota_exists() ) {
		throw new RuntimeException( 'External cron CI fixture unexpectedly already present.' );
	}
	$db->execute(
		"INSERT INTO %i (option_name, option_value, autoload) VALUES (%s,%s,'off')",
		array( $wpdb->options, $key, '3:' . ( $now - 3 * DAY_IN_SECONDS ) )
	);
	$actions = array(
		'target'           => as_schedule_single_action( $now - 60, $hook, array(), $group, false ),
		'unrelated_hook'   => as_schedule_single_action( $now - 60, $otherhook, array(), $group, false ),
		'unrelated_group'  => as_schedule_single_action( $now - 60, $hook, array(), $other, false ),
	);
	foreach ( $actions as $label => $id ) {
		if ( ! is_int( $id ) || $id < 1 ) {
			throw new RuntimeException( 'Unable to schedule isolated action: ' . $label );
		}
	}
	update_option( $ids_key, $actions, false );
	if ( ! $quota_exists() ) {
		throw new RuntimeException( 'Synthetic expired quota was not stored.' );
	}
	echo "PASS: prepared three due CI queue actions, one target and two isolated decoys; real expired quota fixture exists.\n";
	return;
}

if ( 'verify' === $mode ) {
	$actions = get_option( $ids_key, false );
	if ( ! is_array( $actions )
		|| ! isset( $actions['target'], $actions['unrelated_hook'], $actions['unrelated_group'] ) ) {
		throw new RuntimeException( 'External cron CI action IDs are missing.' );
	}
	$target    = (int) $actions['target'];
	$other_id  = (int) $actions['unrelated_hook'];
	$group_id  = (int) $actions['unrelated_group'];
	if ( ! $action_status( $hook, $group, 'complete', $target ) ) {
		throw new RuntimeException( 'Independent WP-CLI Action Scheduler command did not complete its targeted due action.' );
	}
	if ( $quota_exists() ) {
		throw new RuntimeException( 'Independent CLI Cron command failed to remove the expired Guardian quota.' );
	}
	if ( ! $action_status( $otherhook, $group, 'pending', $other_id )
		|| ! $action_status( $hook, $other, 'pending', $group_id ) ) {
		throw new RuntimeException( 'CLI must not process another hook or another Action Scheduler group.' );
	}
	if ( 'ok' !== GuardianQuotaScheduleHealth::inspect( time() ) ) {
		throw new RuntimeException( 'Guardian health deteriorated after an independent CLI-dispatched action.' );
	}
	// This disposable site is discarded after CI; remove only our decoys.
	as_unschedule_action( $otherhook, array(), $group );
	as_unschedule_action( $hook, array(), $other );
	delete_option( $ids_key );
	echo "PASS: independent WP-CLI dispatched exactly the Guardian hook/group; deleted expired quota; decoys remained pending and manager health stayed healthy.\n";
	return;
}
throw new RuntimeException( 'Unknown or missing external Cron CI mode.' );
