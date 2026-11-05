<?php
/**
 * Organization-bound field definitions.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use InvalidArgumentException;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use UOP\Domain\Profiles\FieldRules;

/** Prevents type changes after the first recorded value. */
final class ProfileFieldRepository extends ScopedRepository {
	/**
	 * Construct scoped field lookup.
	 *
	 * @param Connection $db Database connection.
	 * @param string     $prefix Trusted table prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {
		parent::__construct(
			$db,
			$prefix . 'profile_fields',
			array( 'id', 'public_id', 'organization_id', 'field_key', 'data_type', 'label', 'sensitivity', 'subject_view', 'subject_edit', 'delegate_view', 'delegate_edit', 'privacy_purpose', 'retention_class', 'settings_json', 'status' )
		);
	}

	/**
	 * Find a definition only inside the target organization.
	 *
	 * @param OrgScope $scope Organization context.
	 * @param int      $id    Internal field ID.
	 * @return array<string, mixed>|null
	 */
	public function by_internal_id( OrgScope $scope, int $id ): ?array {
		$rows = $this->db->rows(
			'SELECT id, public_id, organization_id, field_key, data_type, label, sensitivity, subject_view, subject_edit, delegate_view, delegate_edit, settings_json, status FROM %i WHERE organization_id = %d AND id = %d LIMIT 1',
			array( $this->prefix . 'profile_fields', $scope->id, $id )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Insert validated author metadata, never write an arbitrary request object.
	 *
	 * @param OrgScope             $scope Scoped organization.
	 * @param PublicId             $id    Opaque public ID.
	 * @param array<string, mixed> $definition Strict allowlisted definition.
	 * @param string               $utc_now UTC timestamp.
	 */
	public function create( OrgScope $scope, PublicId $id, array $definition, string $utc_now ): void {
		FieldRules::validate( $definition );
		$settings = wp_json_encode( array( 'options' => $definition['options'] ?? array() ), JSON_THROW_ON_ERROR );
		$this->db->execute(
			'INSERT INTO %i (public_id, organization_id, field_key, data_type, label, sensitivity, subject_view, subject_edit, delegate_view, delegate_edit, privacy_purpose, lawful_basis_note, retention_class, settings_json, status, created_at, updated_at) VALUES (%s,%d,%s,%s,%s,%s,%d,%d,%d,%d,%s,%s,%s,%s,%s,%s,%s)',
			array(
				$this->prefix . 'profile_fields',
				$id->to_binary(),
				$scope->id,
				$definition['key'],
				$definition['type'],
				$definition['label'],
				$definition['sensitivity'] ?? 'personal',
				(int) ( $definition['subject_view'] ?? true ),
				(int) ( $definition['subject_edit'] ?? true ),
				(int) ( $definition['delegate_view'] ?? false ),
				(int) ( $definition['delegate_edit'] ?? false ),
				$definition['privacy_purpose'] ?? '',
				$definition['lawful_basis_note'] ?? '',
				$definition['retention_class'] ?? '',
				$settings,
				'active',
				$utc_now,
				$utc_now,
			)
		);
	}

	/**
	 * Reject a type change once any value exists; no silent destructive conversion.
	 *
	 * @param OrgScope $scope Organization context.
	 * @param PublicId $id    Field public ID.
	 * @param string   $type  Proposed field type.
	 * @return bool True only for unchanged types or unused fields.
	 * @throws InvalidArgumentException For unsupported types.
	 */
	public function may_change_type( OrgScope $scope, PublicId $id, string $type ): bool {
		if ( ! in_array( $type, FieldRules::types(), true ) ) {
			throw new InvalidArgumentException( 'Unsupported field type.' );
		}
		$field = $this->find( $scope, $id );
		if ( ! $field ) {
			return false;
		}
		if ( $field['data_type'] === $type ) {
			return true;
		}
		$rows = $this->db->rows(
			'SELECT id FROM %i WHERE field_id = %d LIMIT 1',
			array( $this->prefix . 'profile_values', (int) $field['id'] )
		);
		return ! $rows;
	}
}
