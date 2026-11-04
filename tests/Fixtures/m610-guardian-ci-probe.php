<?php
/**
 * Read-only synthetic guardian claim proof for ephemeral HTTPS browser tests.
 *
 * No real identifiers, names or contact information are emitted.
 */
if ( '1' !== getenv( 'UOP_M610_CI' ) || 'cli' !== PHP_SAPI || 'https://127.0.0.1:8443' !== home_url() ) {
	throw new RuntimeException( 'Refusing guardian probe outside disposable CI.' );
}
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\WpdbConnection;

global $wpdb;
$scope  = new OrgScope( (int) get_option( 'uop_default_organization_id' ) );
$db     = new WpdbConnection( $wpdb );
$people = new PersonRepository( $db, $wpdb->prefix . 'uop_' );
$user   = get_user_by( 'login', 'uop_ci_review_parent' );
$linked = $user ? $people->by_user( $scope, (int) $user->ID ) : null;
$raw    = (string) get_option( 'uop_m610_ci_review_child', '' );
$child  = $raw ? $people->find( $scope, PublicId::from_string( $raw ) ) : null;
$key    = $user ? 'uop_gclaim_q2_' . hash( 'sha256', $scope->id . ':' . $user->ID ) : '';
$quota  = $user ? $db->rows(
	'SELECT option_value FROM %i WHERE option_name = %s',
	array( $wpdb->options, $key )
) : array();
$rate = 0;
if ( isset( $quota[0]['option_value'] ) && preg_match( '/^([0-3]):[0-9]{1,12}$/D', (string) $quota[0]['option_value'], $parts ) ) {
	$rate = (int) $parts[1];
}
$count  = $db->rows(
	"SELECT COUNT(*) AS n FROM %i WHERE organization_id = %d AND from_person_id = %d AND type_key = 'guardian_of' AND status = 'pending'",
	array( $wpdb->prefix . 'uop_relationships', $scope->id, (int) ( $linked['id'] ?? 0 ) )
);
echo wp_json_encode(
	array(
		'account_linked' => (bool) $linked,
		'self_active'    => 'active' === ( $linked['status'] ?? '' ),
		'child_found'    => (bool) $child,
		'child_active'   => 'active' === ( $child['status'] ?? '' ),
		'pending_count'  => (int) ( $count[0]['n'] ?? 0 ),
		'claim_attempts' => $rate,
	)
);
