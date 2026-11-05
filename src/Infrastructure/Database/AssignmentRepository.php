<?php
/**
 * Active actor assignments used by the policy boundary.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use UOP\Domain\Organization\OrgScope;

/** Validated organization-owned service. */
final class AssignmentRepository {
	/**
	 * Initialize required dependencies and validated values.
	 *
	 * @param Connection $db db input.
	 * @param string     $prefix prefix input.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Query non-expired actor assignments without caching.
	 *
	 * @param OrgScope $scope scope input.
	 * @param int      $user_id user id input.
	 * @return list<array<string, mixed>>
	 */
	public function active_for( OrgScope $scope, int $user_id ): array {
		if ( $user_id < 1 ) {
			return array();
		}
		return $this->db->rows(
			"SELECT id, role_key, scope_type, scope_id, sensitivity_ceiling FROM %i WHERE organization_id = %d AND user_id = %d AND status = 'active' AND (valid_from IS NULL OR valid_from <= UTC_TIMESTAMP()) AND (valid_to IS NULL OR valid_to > UTC_TIMESTAMP()) ORDER BY id ASC LIMIT 100",
			array( $this->prefix . 'actor_assignments', $scope->id, $user_id )
		);
	}

	/**
	 * Revoke an actor's live grants within an organization.
	 *
	 * @param OrgScope $scope scope input.
	 * @param int      $user_id user id input.
	 * @param string   $utc_now utc now input.
	 * @return int
	 */
	public function revoke_for_actor( OrgScope $scope, int $user_id, string $utc_now ): int {
		return $this->db->execute(
			"UPDATE %i SET status = 'revoked', updated_at = %s WHERE organization_id = %d AND user_id = %d AND status = 'active'",
			array( $this->prefix . 'actor_assignments', $utc_now, $scope->id, $user_id )
		);
	}
	/**
	 * Persist an authorized scoped assignment without granting WordPress roles.
	 *
	 * @param OrgScope $scope      Trusted organization.
	 * @param int      $user_id    Existing WordPress user.
	 * @param string   $role_key   Provisioned business role.
	 * @param string   $scope_type Organization or event scope.
	 * @param int      $scope_id   Zero for organization or event ID.
	 * @param string   $ceiling    Sensitivity ceiling.
	 * @param string   $utc_now    UTC timestamp.
	 * @return int Assignment primary key, internal only.
	 * @throws \InvalidArgumentException For unsupported grant metadata.
	 */
	public function grant( OrgScope $scope, int $user_id, string $role_key, string $scope_type, int $scope_id, string $ceiling, string $utc_now ): int {
		if ( $user_id < 1 || ! in_array( $role_key, array( 'org_manager', 'event_manager', 'staff', 'viewer' ), true )
			|| ! in_array( $ceiling, array( 'public', 'internal', 'personal', 'sensitive', 'medical' ), true )
			|| ! in_array( $scope_type, array( 'organization', 'event' ), true )
			|| ( 'organization' === $scope_type && 0 !== $scope_id )
			|| ( 'event' === $scope_type && $scope_id < 1 ) ) {
			throw new \InvalidArgumentException( 'Invalid actor assignment.' );
		}
		$this->db->execute(
			"INSERT INTO %i (organization_id, user_id, role_key, scope_type, scope_id, sensitivity_ceiling, status, created_at, updated_at) VALUES (%d,%d,%s,%s,%d,%s,'active',%s,%s) ON DUPLICATE KEY UPDATE sensitivity_ceiling = VALUES(sensitivity_ceiling), status = 'active', valid_from = NULL, valid_to = NULL, updated_at = VALUES(updated_at)",
			array( $this->prefix . 'actor_assignments', $scope->id, $user_id, $role_key, $scope_type, $scope_id, $ceiling, $utc_now, $utc_now )
		);
		$rows = $this->db->rows(
			'SELECT id FROM %i WHERE organization_id = %d AND user_id = %d AND role_key = %s AND scope_type = %s AND scope_id = %d LIMIT 1',
			array( $this->prefix . 'actor_assignments', $scope->id, $user_id, $role_key, $scope_type, $scope_id )
		);
		return (int) $rows[0]['id'];
	}

	/**
	 * Revoke an assignment by its internal ID within the trusted organization.
	 *
	 * @param OrgScope $scope   Trusted organization.
	 * @param int      $id      Internal assignment ID.
	 * @param string   $utc_now UTC timestamp.
	 * @return bool Whether the row was live and changed.
	 */
	public function revoke( OrgScope $scope, int $id, string $utc_now ): bool {
		return 1 === $this->db->execute(
			"UPDATE %i SET status = 'revoked', updated_at = %s WHERE organization_id = %d AND id = %d AND status = 'active'",
			array( $this->prefix . 'actor_assignments', $utc_now, $scope->id, $id )
		);
	}
	/**
	 * Resolve an assignment by internal ID only inside the current organization.
	 *
	 * @param OrgScope $scope Trusted organization scope.
	 * @param int      $id    Internal assignment identity.
	 * @return array<string, mixed>|null
	 */
	public function find( OrgScope $scope, int $id ): ?array {
		$rows = $this->db->rows(
			'SELECT id, organization_id, user_id, role_key, scope_type, scope_id, sensitivity_ceiling, status FROM %i WHERE organization_id = %d AND id = %d LIMIT 1',
			array( $this->prefix . 'actor_assignments', $scope->id, $id )
		);
		return $rows[0] ?? null;
	}
}
