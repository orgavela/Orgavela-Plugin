<?php
/**
 * Manually scheduled event occurrences, stored in UTC.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use InvalidArgumentException;
use UOP\Core\PublicId;
use UOP\Domain\Events\OccurrenceWindow;
use UOP\Domain\Organization\OrgScope;

/** No recurring schedule engine or locally ambiguous timestamps. */
final class OccurrenceRepository extends ScopedRepository {
	/**
	 * Bind an owner-scoped occurrence lookup.
	 *
	 * @param Connection $db     Database adapter.
	 * @param string     $prefix Trusted prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {
		parent::__construct( $db, $prefix . 'event_occurrences', array( 'id', 'public_id', 'organization_id', 'event_post_id', 'start_at', 'end_at', 'timezone', 'status' ) );
	}

	/**
	 * Verify the event scope, then save a manually specified UTC window.
	 *
	 * @param OrgScope         $scope Scoped organization.
	 * @param PublicId         $uuid  Public occurrence identity.
	 * @param int              $post_id WordPress event ID.
	 * @param OccurrenceWindow $window Strict DST-aware occurrence interval.
	 * @param string           $utc_now Current UTC timestamp.
	 * @throws InvalidArgumentException If event belongs to another organization.
	 */
	public function create( OrgScope $scope, PublicId $uuid, int $post_id, OccurrenceWindow $window, string $utc_now ): void {
		$event = $this->db->rows(
			"SELECT event_post_id FROM %i WHERE organization_id = %d AND event_post_id = %d AND status = 'active' LIMIT 1",
			array( $this->prefix . 'event_settings', $scope->id, $post_id )
		);
		if ( ! $event ) {
			throw new InvalidArgumentException( 'Occurrence event must belong to the same active organization.' );
		}
		$this->db->execute(
			'INSERT INTO %i (public_id, organization_id, event_post_id, start_at, end_at, timezone, status, created_at, updated_at) VALUES (%s,%d,%d,%s,%s,%s,%s,%s,%s)',
			array( $this->prefix . 'event_occurrences', $uuid->to_binary(), $scope->id, $post_id, $window->start_utc(), $window->end_utc(), $window->zone, 'scheduled', $utc_now, $utc_now )
		);
	}
	/**
	 * List the first scoped event occurrence page for legacy callers.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param int      $event_post_id Trusted scoped event post ID.
	 * @return list<array<string,mixed>>
	 */
	public function for_event( OrgScope $scope, int $event_post_id ): array {
		return $this->for_event_page( $scope, $event_post_id );
	}

	/**
	 * Read a bounded chronological page, using the indexed UTC start and ID.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param int      $event_post_id Scoped event post ID.
	 * @param string|null $after_start UTC start of an event-owned cursor.
	 * @param int      $after_id Internal cursor ID resolved within this event.
	 * @return list<array<string,mixed>>
	 * @throws InvalidArgumentException If cursor components are invalid.
	 */
	public function for_event_page( OrgScope $scope, int $event_post_id, ?string $after_start = null, int $after_id = 0 ): array {
		if ( $event_post_id < 1 || $after_id < 0 || ( null === $after_start && 0 !== $after_id ) || ( null !== $after_start && ( $after_id < 1 || ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D', $after_start ) ) ) ) {
			throw new InvalidArgumentException( 'Invalid scoped occurrence page cursor.' );
		}
		$sql  = 'SELECT public_id, start_at, end_at, timezone, status FROM %i WHERE organization_id = %d AND event_post_id = %d';
		$args = array( $this->prefix . 'event_occurrences', $scope->id, $event_post_id );
		if ( null !== $after_start ) {
			$sql   .= ' AND (start_at > %s OR (start_at = %s AND id > %d))';
			$args[] = $after_start;
			$args[] = $after_start;
			$args[] = $after_id;
		}
		$sql .= ' ORDER BY start_at ASC, id ASC LIMIT 100';
		return $this->db->rows( $sql, $args );
	}

	/**
	 * Resolve a public cursor only inside its owning organization and event.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param int      $event_post_id Trusted scoped WordPress event post.
	 * @param PublicId $uuid Public occurrence UUID.
	 * @return array<string,mixed>|null
	 */
	public function event_cursor( OrgScope $scope, int $event_post_id, PublicId $uuid ): ?array {
		$rows = $this->db->rows(
			'SELECT id, start_at FROM %i WHERE organization_id = %d AND event_post_id = %d AND public_id = %s LIMIT 1',
			array( $this->prefix . 'event_occurrences', $scope->id, $event_post_id, $uuid->to_binary() )
		);
		return $rows[0] ?? null;
	}
}
