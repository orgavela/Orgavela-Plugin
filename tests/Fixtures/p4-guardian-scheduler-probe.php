<?php
/**
 * Real Action Scheduler dispatch probe for disposable P4 Guardian retention.
 *
 * @package UOP
 */

use UOP\Infrastructure\Database\WpdbConnection;
use UOP\Infrastructure\Queue\GuardianQuotaScheduleHealth;

// Never run this helper against IONOS, Staging, an ordinary WordPress install,
// a real participant database, or a non-GitHub execution environment.
if ( 'cli' !== PHP_SAPI
	|| 'true' !== getenv( 'GITHUB_ACTIONS' )
	|| '1' !== getenv( 'UOP_TEST_ALLOW_DATABASE' )
	|| '1' !== getenv( 'UOP_P4_SCHEDULER_PROBE' )
	|| '/tmp/uop-wordpress' !== getenv( 'WP_ROOT' )
	|| 'http://127.0.0.1:8080' !== home_url()
	|| ! defined( 'DISABLE_WP_CRON' )
	|| ! DISABLE_WP_CRON ) {
	throw new RuntimeException( 'Refusing Guardian scheduler probe outside isolated GitHub WordPress CI.' );
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( ! is_plugin_active( 'uop-core/uop-core.php' )
	|| ! function_exists( 'as_get_scheduled_actions' )
	|| ! function_exists( 'as_schedule_single_action' )
	|| ! class_exists( 'ActionScheduler_QueueRunner' ) ) {
	throw new RuntimeException( 'Active UOP and Action Scheduler are required for Guardian dispatch proof.' );
}

global $wpdb;
$db     = new WpdbConnection( $wpdb );
$hook   = 'uop_guardian_quota_prune';
$group  = 'uop';
$prefix = 'uop_gclaim_q2_';
$now    = time();
$kind   = (string) getenv( 'UOP_P4_PROBE_ACTION' );

// Only synthetic hashes; no names, identities, emails or person UUIDs.
$keys = array();
foreach ( array( 'expired', 'active', 'grace', 'invalid', 'unrelated' ) as $label ) {
	$keys[ $label ] = ( 'unrelated' === $label ? 'uopXgclaimXq2X' : $prefix )
		. hash( 'sha256', 'uop_scheduler_ci_fixture:' . $label );
}
$pending = static function () use ( $hook, $group ): array {
	return as_get_scheduled_actions(
		array(
			'hook'     => $hook,
			'group'    => $group,
			'status'   => 'pending',
			'per_page' => 50,
		),
		'ids'
	);
};
$option_value = static function ( string $key ) use ( $db, $wpdb ): ?string {
	$rows = $db->rows(
		'SELECT option_value FROM %i WHERE option_name = %s',
		array( $wpdb->options, $key )
	);
	return $rows ? (string) $rows[0]['option_value'] : null;
};

if ( 'seed' === $kind ) {
	if ( 'first_run' !== GuardianQuotaScheduleHealth::inspect( $now ) ) {
		throw new RuntimeException( 'A scheduled but never dispatched cleanup must be visibly pending, not healthy.' );
	}
	$actions = $pending();
	if ( 1 !== count( $actions ) || false === as_next_scheduled_action( $hook, array(), $group ) ) {
		throw new RuntimeException( 'Expected exactly one recurring Guardian retention job after WordPress bootstrap.' );
	}
	$values = array(
		'expired'   => '3:' . ( $now - 3 * DAY_IN_SECONDS ),
		'active'    => '3:' . ( $now + DAY_IN_SECONDS ),
		'grace'     => '3:' . ( $now - HOUR_IN_SECONDS ),
		'invalid'   => 'invalid-quota-value',
		'unrelated' => '3:' . ( $now - 3 * DAY_IN_SECONDS ),
	);
	foreach ( $values as $label => $value ) {
		$db->execute(
			"INSERT INTO %i (option_name, option_value, autoload) VALUES (%s,%s,'off') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
			array( $wpdb->options, $keys[ $label ], $value )
		);
	}
	echo "PASS: one uniquely scheduled recurring Guardian job and five synthetic quota fixtures.\n";
	return;
}

if ( 'dispatch' === $kind ) {
	// A second fresh WP-CLI process ensures bootstrap did not schedule a
	// duplicate job just because a prior CLI request already initialized it.
	if ( 1 !== count( $pending() )
		|| null === $option_value( $keys['expired'] )
		|| null === $option_value( $keys['invalid'] ) ) {
		throw new RuntimeException( 'Recurring schedule duplication or fixture loss on new bootstrap.' );
	}
	// The real Action Scheduler queue runner must execute this due action.
	// No external site scheduler is assumed; this is disposable WP-CLI CI.
	$due = as_schedule_single_action( $now - 60, $hook, array(), $group, false );
	if ( ! is_int( $due ) || $due < 1 ) {
		throw new RuntimeException( 'Could not create due Guardian retention queue action.' );
	}
	ActionScheduler_QueueRunner::instance()->run();
	$completed = as_get_scheduled_actions(
		array( 'hook' => $hook, 'group' => $group, 'status' => 'complete', 'per_page' => 100 ),
		'ids'
	);
	if ( ! in_array( $due, array_map( 'intval', $completed ), true ) ) {
		throw new RuntimeException( 'Action Scheduler did not complete the due Guardian retention job.' );
	}
	if ( null !== $option_value( $keys['expired'] ) ) {
		throw new RuntimeException( 'The due scheduler dispatch failed to delete aged-out Guardian quota.' );
	}
	foreach ( array( 'active', 'grace', 'invalid', 'unrelated' ) as $label ) {
		if ( null === $option_value( $keys[ $label ] ) ) {
			throw new RuntimeException( 'Guardian retention erased a protected or unrelated option: ' . $label );
		}
	}
	if ( 1 !== count( $pending() ) ) {
		throw new RuntimeException( 'Running a one-off Guardian cleanup must preserve the recurring job.' );
	}
		if ( 'ok' !== GuardianQuotaScheduleHealth::inspect( time() ) ) {
		throw new RuntimeException( 'Completed scheduler dispatch must yield healthy read-only queue status.' );
	}
	echo "PASS: actual Action Scheduler runner completed due Guardian cleanup; expired removed; active/grace/invalid/unrelated retained; recurring job remains unique.\n";
	// Remove only disposable fixture keys; never operate on other site data.
	foreach ( $keys as $key ) {
		$db->execute( 'DELETE FROM %i WHERE option_name = %s', array( $wpdb->options, $key ) );
	}
	return;
}
throw new RuntimeException( 'Unknown disposable Guardian retention probe action.' );
