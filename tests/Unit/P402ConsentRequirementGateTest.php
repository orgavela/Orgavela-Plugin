<?php
/**
 * P4-02 synthetic unit coverage for the isolated trusted-facts gate.
 *
 * @package UOP
 */

namespace UOP\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use UOP\Application\Consent\ConsentRequirementGate;

/**
 * Nonproduction policy-prototype unit tests, with no WordPress or database.
 */
final class P402ConsentRequirementGateTest extends TestCase {
	/** @return array<string,int> */
	private function scope(): array {
		return array(
			'organization_id'       => 7,
			'event_post_id'         => 13,
			'registration_id'       => 29,
			'subject_person_id'     => 41,
			'definition_version_id' => 53,
		);
	}

	/** @return array<string,mixed> */
	private function decision( string $role, int $actor_id ): array {
		return $this->scope() + array(
			'role'              => $role,
			'actor_user_id'     => $actor_id,
			'decision'          => 'granted',
			'current'           => true,
			'authorized'        => true,
			'guardian_verified' => 'representative' === $role,
		);
	}

	public function test_self_and_representative_are_independent_modes(): void {
		$gate = new ConsentRequirementGate();
		self::assertTrue( $gate->satisfied( 'self', $this->scope(), array( $this->decision( 'self', 101 ) ) ) );
		self::assertTrue( $gate->satisfied( 'representative', $this->scope(), array( $this->decision( 'representative', 202 ) ) ) );
		self::assertTrue( $gate->satisfied( 'both', $this->scope(), array( $this->decision( 'self', 101 ), $this->decision( 'representative', 202 ) ) ) );
	}

	public function test_both_requires_two_separate_wordpress_actors(): void {
		$gate = new ConsentRequirementGate();
		self::assertFalse( $gate->satisfied( 'both', $this->scope(), array( $this->decision( 'self', 101 ) ) ) );
		self::assertFalse( $gate->satisfied( 'both', $this->scope(), array( $this->decision( 'self', 101 ), $this->decision( 'representative', 101 ) ) ) );
		self::assertFalse( $gate->satisfied( 'both', $this->scope(), array() ) );
	}

	public function test_unauthorized_or_unverified_representative_never_grants(): void {
		$gate      = new ConsentRequirementGate();
		$own       = $this->decision( 'self', 101 );
		$guardian  = $this->decision( 'representative', 202 );
		self::assertFalse( $gate->satisfied( 'both', $this->scope(), array( $own, array_replace( $guardian, array( 'guardian_verified' => false ) ) ) ) );
		self::assertFalse( $gate->satisfied( 'both', $this->scope(), array( $own, array_replace( $guardian, array( 'authorized' => false ) ) ) ) );
		self::assertFalse( $gate->satisfied( 'both', $this->scope(), array( $own, array_replace( $guardian, array( 'current' => false ) ) ) ) );
	}

	public function test_withdrawal_and_denial_invalidate_current_consent(): void {
		$gate = new ConsentRequirementGate();
		$own  = $this->decision( 'self', 101 );
		$rep  = $this->decision( 'representative', 202 );
		self::assertFalse( $gate->satisfied( 'both', $this->scope(), array( array_replace( $own, array( 'decision' => 'withdrawn' ) ), $rep ) ) );
		self::assertFalse( $gate->satisfied( 'representative', $this->scope(), array( array_replace( $rep, array( 'decision' => 'denied' ) ) ) ) );
	}

	public function test_wrong_subject_event_registration_and_version_are_denied(): void {
		$gate = new ConsentRequirementGate();
		$own  = $this->decision( 'self', 101 );
		$rep  = $this->decision( 'representative', 202 );
		foreach ( array( 'organization_id', 'subject_person_id', 'registration_id', 'event_post_id', 'definition_version_id' ) as $field ) {
			self::assertFalse( $gate->satisfied( 'both', $this->scope(), array( $own, array_replace( $rep, array( $field => $rep[ $field ] + 1 ) ) ) ) );
		}
	}

	public function test_duplicated_role_fails_closed(): void {
		$gate = new ConsentRequirementGate();
		self::assertFalse( $gate->satisfied( 'both', $this->scope(), array( $this->decision( 'self', 101 ), $this->decision( 'self', 102 ) ) ) );
	}

	public function test_untrusted_actor_type_is_rejected(): void {
		$gate = new ConsentRequirementGate();
		$bad  = array_replace( $this->decision( 'self', 101 ), array( 'actor_user_id' => '101' ) );
		$this->expectException( InvalidArgumentException::class );
		$gate->satisfied( 'self', $this->scope(), array( $bad ) );
	}

	public function test_unknown_mode_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		( new ConsentRequirementGate() )->satisfied( 'by_email', $this->scope(), array( $this->decision( 'self', 101 ) ) );
	}
}
