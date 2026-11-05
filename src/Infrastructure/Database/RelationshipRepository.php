<?php
/**
 * Non-authorizing person relationships.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use InvalidArgumentException;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/** A relationship is evidence of a link, never a permission grant. */
final class RelationshipRepository {
	/**
	 * Initialize required dependencies and validated values.
	 *
	 * @param Connection $db db input.
	 * @param string     $prefix prefix input.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Find an organization-owned resource by public ID.
	 *
	 * @param OrgScope $scope scope input.
	 * @param PublicId $id id input.
	 * @return array<string, mixed>|null
	 */
	public function find( OrgScope $scope, PublicId $id ): ?array {
		$rows = $this->db->rows(
			'SELECT id, organization_id, from_person_id, to_person_id, type_key, status, valid_from, valid_to FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1',
			array( $this->prefix . 'relationships', $scope->id, $id->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Create a validated organization-owned record.
	 *
	 * @param OrgScope $scope scope input.
	 * @param PublicId $id id input.
	 * @param int      $from_id from id input.
	 * @param int      $to_id to id input.
	 * @param string   $type type input.
	 * @param string   $utc_now utc now input.
	 * @throws \InvalidArgumentException When input violates invariants.
	 */
	public function create( OrgScope $scope, PublicId $id, int $from_id, int $to_id, string $type, string $utc_now ): void {
		if ( $from_id < 1 || $to_id < 1 || $from_id === $to_id || ! in_array( $type, array( 'guardian_of', 'representative_of' ), true ) ) {
			throw new InvalidArgumentException( 'Invalid relationship.' );
		}
		$people = $this->db->rows(
			"SELECT id FROM %i WHERE organization_id = %d AND status = 'active' AND id IN (%d, %d)",
			array( $this->prefix . 'persons', $scope->id, $from_id, $to_id )
		);
		if ( 2 !== count( $people ) ) {
			throw new InvalidArgumentException( 'Relationship subjects are outside the organization.' );
		}
		$this->db->execute(
			'INSERT INTO %i (public_id, organization_id, from_person_id, to_person_id, type_key, status, created_at, updated_at) VALUES (%s, %d, %d, %d, %s, %s, %s, %s)',
			array( $this->prefix . 'relationships', $id->to_binary(), $scope->id, $from_id, $to_id, $type, 'active', $utc_now, $utc_now )
		);
	}
}
