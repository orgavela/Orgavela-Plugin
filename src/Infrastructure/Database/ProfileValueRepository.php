<?php
/**
 * Typed profile value persistence inside a transaction.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use InvalidArgumentException;
use UOP\Domain\Organization\OrgScope;

/** Exactly one typed value slot per row and an ordinal for multiselect. */
final class ProfileValueRepository {
	/**
	 * Capture the live database and owned prefix.
	 *
	 * @param Connection $db     Database adapter.
	 * @param string     $prefix Trusted WordPress prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Replace values for a verified organization-owned person and field.
	 *
	 * @param OrgScope $scope Organization boundary.
	 * @param int      $person_id Internal person ID.
	 * @param int      $field_id  Internal field ID.
	 * @param array    $values Typed normalized values.
	 * @phpstan-param list<array{slot:string,value:string|int,ordinal:int}> $values
	 * @param string   $utc_now UTC timestamp.
	 * @throws InvalidArgumentException If a value escapes its scalar slot.
	 */
	public function replace( OrgScope $scope, int $person_id, int $field_id, array $values, string $utc_now ): void {
		$owned = $this->db->rows(
			"SELECT p.id FROM %i p INNER JOIN %i f ON f.organization_id = p.organization_id WHERE p.organization_id = %d AND p.id = %d AND f.id = %d AND p.status = 'active' AND f.status = 'active' LIMIT 1",
			array( $this->prefix . 'persons', $this->prefix . 'profile_fields', $scope->id, $person_id, $field_id )
		);
		if ( ! $owned ) {
			throw new InvalidArgumentException( 'Profile person and field must belong to the same active organization.' );
		}
		$this->db->execute(
			'DELETE FROM %i WHERE person_id = %d AND field_id = %d',
			array( $this->prefix . 'profile_values', $person_id, $field_id )
		);
		foreach ( $values as $value ) {
			$slot = $value['slot'];
			if ( ! in_array( $slot, array( 'value_string', 'value_text', 'value_decimal', 'value_date', 'value_boolean' ), true ) || $value['ordinal'] < 0 || $value['ordinal'] > 100 ) {
				throw new InvalidArgumentException( 'Unsupported profile value slot.' );
			}
			$this->db->execute(
				'INSERT INTO %i (person_id, field_id, ordinal, %i, updated_at) VALUES (%d,%d,%d,%s,%s)',
				array( $this->prefix . 'profile_values', $slot, $person_id, $field_id, $value['ordinal'], $value['value'], $utc_now )
			);
		}
	}
	/**
	 * Read typed values for a scoped person with organization-bound field joins.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param int      $person_id Internal subject ID already scoped in the application.
	 * @return list<array<string, mixed>>
	 */
	public function for_person( OrgScope $scope, int $person_id ): array {
		return $this->db->rows(
			"SELECT v.field_id, v.ordinal, v.value_string, v.value_text, v.value_decimal, v.value_date, v.value_boolean FROM %i v INNER JOIN %i f ON f.id = v.field_id INNER JOIN %i p ON p.id = v.person_id WHERE p.organization_id = %d AND f.organization_id = %d AND p.id = %d AND p.status = 'active' AND f.status = 'active' ORDER BY v.field_id, v.ordinal LIMIT 1000",
			array( $this->prefix . 'profile_values', $this->prefix . 'profile_fields', $this->prefix . 'persons', $scope->id, $scope->id, $person_id )
		);
	}
}
