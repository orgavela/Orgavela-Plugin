<?php
/**
 * P4-02 no-WordPress/no-database smoke tests for the proposed two-actor gate.
 *
 * Run: php tests/Unit/p402-consent-gate-smoke.php
 * Only synthetic integer identities and immutable scope placeholders.
 */
require dirname( __DIR__, 2 ) . '/src/Domain/Consent/ConsentRequirementGate.php';

use UOP\Domain\Consent\ConsentRequirementGate;

$gate = new ConsentRequirementGate();
$scope = array(
	'organization_id' => 7,
	'event_post_id' => 13,
	'registration_id' => 29,
	'subject_person_id' => 41,
	'definition_version_id' => 53,
);
$self = $scope + array(
	'role' => 'self', 'actor_user_id' => 101, 'decision' => 'granted',
	'current' => true, 'authorized' => true, 'guardian_verified' => false,
);
$guardian = $scope + array(
	'role' => 'representative', 'actor_user_id' => 202, 'decision' => 'granted',
	'current' => true, 'authorized' => true, 'guardian_verified' => true,
);

$pass = 0;
$check = static function ( string $name, bool $expected, string $mode, array $facts ) use ( $gate, $scope, &$pass ): void {
	$actual = $gate->satisfied( $mode, $scope, $facts );
	if ( $expected !== $actual ) {
		throw new RuntimeException( 'P402 test failed: ' . $name );
	}
	++$pass;
};
$rejected = static function ( string $name, string $mode, array $facts, ?array $trusted_scope = null ) use ( $gate, $scope, &$pass ): void {
	try {
		$gate->satisfied( $mode, $trusted_scope ?? $scope, $facts );
	} catch ( InvalidArgumentException ) {
		++$pass;
		return;
	}
	throw new RuntimeException( 'P402 malformed fact was accepted: ' . $name );
};

$check( 'self only', true, 'self', array( $self ) );
$check( 'representative only', true, 'representative', array( $guardian ) );
$check( 'independent two actors', true, 'both', array( $self, $guardian ) );
$check( 'no decisions', false, 'both', array() );
$check( 'missing representative', false, 'both', array( $self ) );
$check( 'missing self', false, 'both', array( $guardian ) );
$check( 'same WordPress account both roles', false, 'both', array( $self, array_replace( $guardian, array( 'actor_user_id' => 101 ) ) ) );
$check( 'independent guardianship not verified', false, 'both', array( $self, array_replace( $guardian, array( 'guardian_verified' => false ) ) ) );
$check( 'revoked permission cannot pass', false, 'both', array( $self, array_replace( $guardian, array( 'authorized' => false ) ) ) );
$check( 'stale role cannot pass', false, 'both', array( $self, array_replace( $guardian, array( 'current' => false ) ) ) );
$check( 'withdrawn own decision', false, 'both', array( array_replace( $self, array( 'decision' => 'withdrawn' ) ), $guardian ) );
$check( 'explicit denied representative', false, 'representative', array( array_replace( $guardian, array( 'decision' => 'denied' ) ) ) );
$check( 'other organization', false, 'both', array( $self, array_replace( $guardian, array( 'organization_id' => 8 ) ) ) );
$check( 'other event', false, 'both', array( $self, array_replace( $guardian, array( 'event_post_id' => 14 ) ) ) );
$check( 'other registration', false, 'both', array( $self, array_replace( $guardian, array( 'registration_id' => 30 ) ) ) );
$check( 'other subject', false, 'both', array( $self, array_replace( $guardian, array( 'subject_person_id' => 42 ) ) ) );
$check( 'other consent document version', false, 'both', array( $self, array_replace( $guardian, array( 'definition_version_id' => 54 ) ) ) );
$check( 'conflicting multiple self slots', false, 'both', array( $self, array_replace( $guardian, array( 'role' => 'self' ) ) ) );
$rejected( 'unknown policy mode', 'guardian-by-email', array( $self ) );
$rejected( 'untrusted string actor', 'both', array( $self, array_replace( $guardian, array( 'actor_user_id' => '202' ) ) ) );
$rejected( 'untrusted boolean string', 'both', array( $self, array_replace( $guardian, array( 'guardian_verified' => 'true' ) ) ) );
$rejected( 'unexpected client property', 'both', array( $self, $guardian + array( 'email' => 'shared@example.invalid' ) ) );
$rejected( 'missing scope field', 'both', array( $self, $guardian ), array_diff_key( $scope, array( 'event_post_id' => true ) ) );
$rejected( 'invalid tenant identity', 'both', array( $self, $guardian ), array_replace( $scope, array( 'organization_id' => 0 ) ) );
$rejected( 'non-list candidate set', 'both', array( 1 => $self, 2 => $guardian ) );

echo 'P402_CONSENT_GATE_SYNTHETIC=' . $pass . '/' . $pass . ' PASS' . PHP_EOL;
echo 'P402_WORDPRESS_DB_REQUESTS=0; P402_PRODUCTION_INTEGRATION=DISABLED' . PHP_EOL;
