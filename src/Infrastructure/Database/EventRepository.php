<?php
/**
 * Event operational settings never compete with WordPress post meta.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/** Event CPT holds editorial content; scoped tables own all operations. */
final class EventRepository {
	/**
	 * Bind the site-scoped database.
	 *
	 * @param Connection $db     Database adapter.
	 * @param string     $prefix Trusted prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Resolve event operations within the organization.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param int      $post_id WordPress event post ID.
	 * @return array<string, mixed>|null
	 */
	public function find( OrgScope $scope, int $post_id ): ?array {
		$rows = $this->db->rows(
			'SELECT event_post_id, public_id, organization_id, status, visibility, timezone, registration_open_at, registration_close_at, default_form_id, version FROM %i WHERE organization_id = %d AND event_post_id = %d LIMIT 1',
			array( $this->prefix . 'event_settings', $scope->id, $post_id )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Insert audited operational settings for an existing CPT record.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $uuid Public settings identity.
	 * @param int      $post_id Already-created CPT post ID.
	 * @param string   $zone IANA event timezone.
	 * @param string   $utc_now UTC timestamp.
	 */
	public function create( OrgScope $scope, PublicId $uuid, int $post_id, string $zone, string $utc_now ): void {
		$this->db->execute(
			'INSERT INTO %i (event_post_id, public_id, organization_id, status, visibility, timezone, review_mode, require_email_verification, created_at, updated_at) VALUES (%d,%s,%d,%s,%s,%s,%s,%d,%s,%s)',
			array( $this->prefix . 'event_settings', $post_id, $uuid->to_binary(), $scope->id, 'active', 'private', $zone, 'manual', 0, $utc_now, $utc_now )
		);
	}
	/**
	 * Lookup an event by its external public ID, not a client-supplied post key.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $uuid  External public ID.
	 * @return array<string, mixed>|null
	 */
	public function by_public( OrgScope $scope, PublicId $uuid ): ?array {
		$rows = $this->db->rows(
			'SELECT event_post_id, public_id, organization_id, status, visibility, timezone, registration_open_at, registration_close_at, version FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1',
			array( $this->prefix . 'event_settings', $scope->id, $uuid->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Enumerate a bounded page of manager-visible organization event settings.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @return list<array<string, mixed>>
	 */
	public function for_organization( OrgScope $scope ): array {
		return $this->db->rows(
			'SELECT event_post_id, public_id, status, visibility, timezone FROM %i WHERE organization_id = %d ORDER BY event_post_id ASC LIMIT 100',
			array( $this->prefix . 'event_settings', $scope->id )
		);
	}
}
