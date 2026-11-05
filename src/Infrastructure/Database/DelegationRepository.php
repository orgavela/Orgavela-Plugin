<?php
/**
 * Live delegation grants; independent from relationships.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use InvalidArgumentException;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/** Validated organization-owned service. */
final class DelegationRepository {
	/**
	 * Initialize required dependencies and validated values.
	 *
	 * @param Connection $db db input.
	 * @param string     $prefix prefix input.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Evaluate the live delegation scope and expiry.
	 *
	 * @param OrgScope $scope scope input.
	 * @param int      $actor_id actor id input.
	 * @param int      $subject_id subject id input.
	 * @param string   $permission permission input.
	 * @param int      $event_id event id input.
	 * @return bool
	 */
	public function allows( OrgScope $scope, int $actor_id, int $subject_id, string $permission, int $event_id = 0 ): bool {
		if ( $actor_id < 1 || $subject_id < 1 ) {
			return false;
		}
		$rows = $this->db->rows(
			"SELECT id FROM %i WHERE organization_id = %d AND actor_user_id = %d AND subject_person_id = %d AND permission_set = %s AND status = 'active' AND (valid_from IS NULL OR valid_from <= UTC_TIMESTAMP()) AND (valid_to IS NULL OR valid_to > UTC_TIMESTAMP()) AND ((scope_type = 'organization' AND scope_id = 0) OR (scope_type = 'event' AND scope_id = %d AND %d > 0)) LIMIT 1",
			array( $this->prefix . 'delegations', $scope->id, $actor_id, $subject_id, $permission, $event_id, $event_id )
		);
		return ! empty( $rows );
	}

	/**
	 * List current delegations for a trusted actor.
	 *
	 * @param OrgScope $scope scope input.
	 * @param int      $actor_id actor id input.
	 * @return list<array<string, mixed>>
	 */
	public function for_actor( OrgScope $scope, int $actor_id ): array {
		return $this->db->rows(
			"SELECT id, public_id, subject_person_id, permission_set, scope_type, scope_id FROM %i WHERE organization_id = %d AND actor_user_id = %d AND status = 'active' AND (valid_from IS NULL OR valid_from <= UTC_TIMESTAMP()) AND (valid_to IS NULL OR valid_to > UTC_TIMESTAMP()) ORDER BY id ASC LIMIT 100",
			array( $this->prefix . 'delegations', $scope->id, $actor_id )
		);
	}

	/**
	 * Create or explicitly reactivate a validated delegation.
	 *
	 * @param OrgScope $scope scope input.
	 * @param PublicId $id id input.
	 * @param int      $actor_id actor id input.
	 * @param int      $subject_id subject id input.
	 * @param string   $permission permission input.
	 * @param string   $scope_type scope type input.
	 * @param int      $scope_id scope id input.
	 * @param int|null $relationship_id relationship id input.
	 * @param string   $utc_now utc now input.
	 * @throws \InvalidArgumentException When input violates invariants.
	 */
	public function grant( OrgScope $scope, PublicId $id, int $actor_id, int $subject_id, string $permission, string $scope_type, int $scope_id, ?int $relationship_id, string $utc_now ): void {
		if ( $actor_id < 1 || $subject_id < 1 || ! in_array( $permission, array( 'registration_manage', 'profile_view', 'profile_edit' ), true ) || ! in_array( $scope_type, array( 'organization', 'event' ), true ) || ( 'organization' === $scope_type && 0 !== $scope_id ) || ( 'event' === $scope_type && $scope_id < 1 ) ) {
			throw new InvalidArgumentException( 'Invalid delegation grant.' );
		}
		$people = $this->db->rows(
			"SELECT id FROM %i WHERE organization_id = %d AND status = 'active' AND id = %d LIMIT 1",
			array( $this->prefix . 'persons', $scope->id, $subject_id )
		);
		if ( ! $people ) {
			throw new InvalidArgumentException( 'Delegation subject is outside the organization.' );
		}
		$this->db->execute(
			"INSERT INTO %i (public_id, organization_id, actor_user_id, subject_person_id, relationship_id, permission_set, scope_type, scope_id, status, created_at) VALUES (%s,%d,%d,%d,NULLIF(%d, 0),%s,%s,%d,'active',%s) ON DUPLICATE KEY UPDATE status = 'active', revoked_at = NULL, valid_from = NULL, valid_to = NULL",
			array( $this->prefix . 'delegations', $id->to_binary(), $scope->id, $actor_id, $subject_id, $relationship_id ?? 0, $permission, $scope_type, $scope_id, $utc_now )
		);
	}

	/**
	 * Revoke an actor's live grants within an organization.
	 *
	 * @param OrgScope $scope scope input.
	 * @param int      $actor_id actor id input.
	 * @param string   $utc_now utc now input.
	 * @return int
	 */
	public function revoke_for_actor( OrgScope $scope, int $actor_id, string $utc_now ): int {
		return $this->db->execute(
			"UPDATE %i SET status = 'revoked', revoked_at = %s WHERE organization_id = %d AND actor_user_id = %d AND status = 'active'",
			array( $this->prefix . 'delegations', $utc_now, $scope->id, $actor_id )
		);
	}

	/**
	 * Revoke one grant within the trusted organization.
	 *
	 * @param OrgScope $scope scope input.
	 * @param PublicId $id id input.
	 * @param string   $utc_now utc now input.
	 * @return bool
	 */
	public function revoke( OrgScope $scope, PublicId $id, string $utc_now ): bool {
		return 1 === $this->db->execute(
			"UPDATE %i SET status = 'revoked', revoked_at = %s WHERE organization_id = %d AND public_id = %s AND status = 'active'",
			array( $this->prefix . 'delegations', $utc_now, $scope->id, $id->to_binary() )
		);
	}
	/**
	 * Resolve the actual durable delegation identity, including explicit regrants.
	 *
	 * @param OrgScope $scope      Trusted organization.
	 * @param int      $actor_id   Delegated WordPress user.
	 * @param int      $subject_id Target person.
	 * @param string   $permission Named permission set.
	 * @param string   $scope_type Organization or event.
	 * @param int      $scope_id   Zero or event post ID.
	 * @return array<string, mixed>|null
	 */
	public function by_grant_key( OrgScope $scope, int $actor_id, int $subject_id, string $permission, string $scope_type, int $scope_id ): ?array {
		$rows = $this->db->rows(
			'SELECT id, public_id, status FROM %i WHERE organization_id = %d AND actor_user_id = %d AND subject_person_id = %d AND permission_set = %s AND scope_type = %s AND scope_id = %d LIMIT 1',
			array( $this->prefix . 'delegations', $scope->id, $actor_id, $subject_id, $permission, $scope_type, $scope_id )
		);
		return $rows[0] ?? null;
	}
	/**
	 * Load one delegation by public identity inside its organization.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $id    Validated public UUID.
	 * @return array<string, mixed>|null
	 */
	public function find( OrgScope $scope, PublicId $id ): ?array {
		$rows = $this->db->rows(
			'SELECT id, public_id, organization_id, actor_user_id, subject_person_id, permission_set, scope_type, scope_id, status FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1',
			array( $this->prefix . 'delegations', $scope->id, $id->to_binary() )
		);
		return $rows[0] ?? null;
	}
}
