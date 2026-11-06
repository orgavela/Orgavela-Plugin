<?php
/**
 * Optimistic drafts and append-only published form versions.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use InvalidArgumentException;
use RuntimeException;
use UOP\Core\PublicId;
use UOP\Domain\Forms\FormSchema;
use UOP\Domain\Organization\OrgScope;

/** Root is mutable; version rows are insert-only and content-addressed. */
final class FormRepository extends ScopedRepository {
	/**
	 * Bind owned forms and immutable versions.
	 *
	 * @param Connection $db Database adapter.
	 * @param string     $prefix Trusted table prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {
		parent::__construct( $db, $prefix . 'forms', array( 'id', 'public_id', 'organization_id', 'form_key', 'title', 'context', 'status', 'draft_schema_json', 'draft_revision', 'current_version_id', 'archived_at' ) );
	}

	/**
	 * Create the draft root with only explicit allowed properties.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $uuid Public form identity.
	 * @param int      $actor_id Actor from WordPress authorization.
	 * @param string   $key Validated immutable form key.
	 * @param string   $title Form title.
	 * @param string   $context Domain context.
	 * @param array    $schema Validated draft schema.
	 * @phpstan-param array<string, mixed> $schema
	 * @param string   $utc_now UTC timestamp.
	 * @throws InvalidArgumentException If author properties are out of contract.
	 */
	public function create( OrgScope $scope, PublicId $uuid, int $actor_id, string $key, string $title, string $context, array $schema, string $utc_now ): void {
		if ( ! preg_match( '/^[a-z][a-z0-9_]{0,99}$/D', $key ) || '' === trim( $title ) || mb_strlen( $title ) > 191 || ! in_array( $context, array( 'event', 'organization' ), true ) ) {
			throw new InvalidArgumentException( 'Invalid form root properties.' );
		}
		( new FormSchema() )->validate_draft( $schema );
		$this->db->execute(
			'INSERT INTO %i (public_id, organization_id, form_key, title, context, status, draft_schema_json, draft_revision, created_by_user_id, created_at, updated_at) VALUES (%s,%d,%s,%s,%s,%s,%s,%d,%d,%s,%s)',
			array( $this->prefix . 'forms', $uuid->to_binary(), $scope->id, $key, $title, $context, 'draft', wp_json_encode( $schema, JSON_THROW_ON_ERROR ), 1, $actor_id, $utc_now, $utc_now )
		);
	}

	/**
	 * Replace only the draft; fail rather than clobber a stale revision.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $form_id Form public ID.
	 * @param int      $expected Client-observed draft revision.
	 * @param array    $schema Validated author DTO.
	 * @phpstan-param array<string, mixed> $schema
	 * @param string   $utc_now UTC timestamp.
	 * @return bool True only when expected revision matched.
	 */
	public function save_draft( OrgScope $scope, PublicId $form_id, int $expected, array $schema, string $utc_now ): bool {
		( new FormSchema() )->validate_draft( $schema );
		if ( $expected < 1 ) {
			return false;
		}
		return 1 === $this->db->execute(
			'UPDATE %i SET draft_schema_json = %s, draft_revision = draft_revision + 1, updated_at = %s WHERE organization_id = %d AND public_id = %s AND draft_revision = %d AND archived_at IS NULL',
			array( $this->prefix . 'forms', wp_json_encode( $schema, JSON_THROW_ON_ERROR ), $utc_now, $scope->id, $form_id->to_binary(), $expected )
		);
	}

	/**
	 * Load one form inside its owning organization while holding its lock.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $uuid Form public ID.
	 * @return array<string,mixed>|null
	 */
	public function lock( OrgScope $scope, PublicId $uuid ): ?array {
		$rows = $this->db->rows(
			'SELECT id, public_id, organization_id, draft_schema_json, draft_revision, current_version_id, archived_at FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1 FOR UPDATE',
			array( $this->prefix . 'forms', $scope->id, $uuid->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Atomically append a version and update the pointer under the root lock.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param array    $root Locked form root.
	 * @phpstan-param array<string, mixed> $root
	 * @param PublicId $uuid Immutable version UUID.
	 * @param array    $published Verified schema with consent pins.
	 * @phpstan-param array<string, mixed> $published
	 * @param int      $actor_id Verified editor.
	 * @param string   $utc_now UTC timestamp.
	 * @return array{id:int,public_id:PublicId,version:int}
	 * @throws RuntimeException When a version cannot be loaded.
	 */
	public function append_published( OrgScope $scope, array $root, PublicId $uuid, array $published, int $actor_id, string $utc_now ): array {
		$rows    = $this->db->rows(
			'SELECT COALESCE(MAX(version),0) AS v FROM %i WHERE form_id = %d',
			array( $this->prefix . 'form_versions', (int) $root['id'] )
		);
		$version = (int) $rows[0]['v'] + 1;
		$json    = wp_json_encode( $published, JSON_THROW_ON_ERROR );
		$this->db->execute(
			'INSERT INTO %i (public_id, form_id, version, schema_json, checksum, created_by_user_id, published_at) VALUES (%s,%d,%d,%s,%s,%d,%s)',
			array( $this->prefix . 'form_versions', $uuid->to_binary(), (int) $root['id'], $version, $json, hash( 'sha256', $json, true ), $actor_id, $utc_now )
		);
		$row = $this->db->rows(
			'SELECT id FROM %i WHERE public_id = %s AND form_id = %d LIMIT 1',
			array( $this->prefix . 'form_versions', $uuid->to_binary(), (int) $root['id'] )
		);
		if ( ! $row ) {
			throw new RuntimeException( 'Form version was not persisted.' );
		}
		$this->db->execute(
			"UPDATE %i SET current_version_id = %d, status = 'published', updated_at = %s WHERE organization_id = %d AND id = %d",
			array( $this->prefix . 'forms', (int) $row[0]['id'], $utc_now, $scope->id, (int) $root['id'] )
		);
		return array(
			'id'        => (int) $row[0]['id'],
			'public_id' => $uuid,
			'version'   => $version,
		);
	}

	/**
	 * Pin only a published consent version belonging to this organization.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $definition_id Consent definition public ID.
	 * @return PublicId|null Immutable consent version or null if inactive.
	 */
	public function current_consent( OrgScope $scope, PublicId $definition_id ): ?PublicId {
		$rows = $this->db->rows(
			"SELECT cv.public_id FROM %i cd INNER JOIN %i cv ON cv.id = cd.current_version_id WHERE cd.organization_id = %d AND cd.public_id = %s AND cd.status = 'active' LIMIT 1",
			array( $this->prefix . 'consent_definitions', $this->prefix . 'consent_versions', $scope->id, $definition_id->to_binary() )
		);
		return $rows ? PublicId::from_binary( $rows[0]['public_id'] ) : null;
	}
	/**
	 * Resolve the published-version pointer only through the form owner scope.
	 *
	 * @param OrgScope $scope Scoped organization.
	 * @param int      $form_id Internal form ID from a scoped read.
	 * @return array<string, mixed>|null
	 */
	public function current_published( OrgScope $scope, int $form_id ): ?array {
		$rows = $this->db->rows(
			'SELECT v.public_id, v.version, v.checksum, v.published_at FROM %i f INNER JOIN %i v ON v.id = f.current_version_id WHERE f.organization_id = %d AND f.id = %d LIMIT 1',
			array( $this->prefix . 'forms', $this->prefix . 'form_versions', $scope->id, $form_id )
		);
		return $rows[0] ?? null;
	}
}
