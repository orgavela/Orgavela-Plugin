<?php
/**
 * Synthetic Guardian REST concurrency data and assertions, disposable CI only.
 *
 * This file is executed by WP-CLI and is not installed in the plugin archive.
 * The test accounts and persons never represent real individuals.
 */
if ( '1' !== getenv( 'UOP_M610_CI' ) || 'cli' !== PHP_SAPI
	|| 'https://127.0.0.1:8443' !== home_url()
	|| ! getenv( 'WP_ROOT' ) || ! getenv( 'GITHUB_ACTIONS' ) ) {
	throw new RuntimeException( 'Refusing guardian quota stress outside disposable GitHub HTTPS CI.' );
}

use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\WpdbConnection;

global $wpdb;
$db      = new WpdbConnection( $wpdb );
$scope   = new OrgScope( (int) get_option( 'uop_default_organization_id' ) );
$prefix  = $wpdb->prefix . 'uop_';
$people  = new PersonRepository( $db, $prefix );
$command = getenv( 'UOP_GUARDIAN_QUOTA_ACTION' );
$user    = get_user_by( 'login', 'uop_ci_quota_parent' );

if ( 'seed' === $command ) {
	$password = (string) getenv( 'WP_ADMIN_PASSWORD' );
	if ( $user || strlen( $password ) < 20 || $scope->id < 1 ) {
		throw new RuntimeException( 'Disposable guardian quota seed preconditions failed.' );
	}
	$user_id = wp_create_user( 'uop_ci_quota_parent', $password, 'quota-parent-ci@example.invalid' );
	if ( ! is_int( $user_id ) ) {
		throw new RuntimeException( 'Synthetic guardian quota user could not be created.' );
	}
	( new WP_User( $user_id ) )->set_role( 'subscriber' );
	$now    = gmdate( 'Y-m-d H:i:s' );
	$parent = PublicId::generate();
	$people->create( $scope, $parent, 'Quota test representative', null, $now );
	if ( ! $people->link( $scope, $parent, $user_id, $now ) ) {
		throw new RuntimeException( 'Synthetic guardian quota person could not be linked.' );
	}
	$targets = array();
	for ( $i = 0; $i < 16; ++$i ) {
		$subject = PublicId::generate();
		$people->create( $scope, $subject, 'Synthetic quota subject ' . $i, null, $now );
		$targets[] = $subject->to_string();
	}
	update_option( 'uop_m610_ci_quota_subjects', $targets, false );
	echo "Prepared 16 synthetic subjects for real REST contention.\n";
	return;
}

if ( ! $user ) {
	throw new RuntimeException( 'Synthetic guardian quota actor is missing.' );
}
$targets = get_option( 'uop_m610_ci_quota_subjects' );
if ( ! is_array( $targets ) || 16 !== count( $targets ) ) {
	throw new RuntimeException( 'Synthetic guardian quota subject fixture is incomplete.' );
}
if ( 'subjects' === $command ) {
	echo wp_json_encode( array_values( $targets ), JSON_THROW_ON_ERROR );
	return;
}
if ( 'verify' === $command ) {
	$key     = 'uop_gclaim_q2_' . hash( 'sha256', $scope->id . ':' . $user->ID );
	$counter = $db->rows(
		'SELECT option_value FROM %i WHERE option_name = %s',
		array( $wpdb->options, $key )
	);
	if ( 1 !== count( $counter ) || ! preg_match( '/^3:([0-9]{1,12})$/D', (string) $counter[0]['option_value'], $match )
		|| (int) $match[1] <= time() ) {
		throw new RuntimeException( 'The durable quota must equal three in the active window.' );
	}
	$linked = $people->by_user( $scope, (int) $user->ID );
	$claims = $db->rows(
		'SELECT COUNT(*) AS n FROM %i WHERE organization_id = %d AND from_person_id = %d AND type_key = %s AND status = %s',
		array( $prefix . 'relationships', $scope->id, (int) $linked['id'], 'guardian_of', 'pending' )
	);
	if ( 3 !== (int) $claims[0]['n'] ) {
		throw new RuntimeException( 'Concurrent REST quota bypass or missing legitimate claim.' );
	}
	$grants = $db->rows(
		'SELECT COUNT(*) AS n FROM %i WHERE organization_id = %d AND actor_user_id = %d',
		array( $prefix . 'delegations', $scope->id, (int) $user->ID )
	);
	if ( 0 !== (int) $grants[0]['n'] ) {
		throw new RuntimeException( 'Pending Guardian claims must never grant delegation.' );
	}
	echo "PASS: 16 concurrent HTTP claims, exactly 3 persisted requests, 0 grants; durable quota is 3.\n";
	return;
}
throw new RuntimeException( 'Unknown disposable guardian quota fixture action.' );
