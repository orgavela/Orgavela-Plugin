<?php
/**
 * Policy-only object reference resolved by an organization-scoped query.
 *
 * @package UOP
 */

namespace UOP\Application\Policy;

use InvalidArgumentException;

/** Not a client-supplied authorization claim. */
final readonly class PolicyObject {
	/**
	 * Initialize required dependencies and validated values.
	 *
	 * @param int      $organization_id organization id input.
	 * @param string   $type type input.
	 * @param int      $id id input.
	 * @param int|null $subject_person_id subject person id input.
	 * @param int|null $event_post_id event post id input.
	 * @param bool     $archived archived input.
	 * @throws \InvalidArgumentException When input violates invariants.
	 */
	public function __construct(
		public int $organization_id,
		public string $type,
		public int $id,
		public ?int $subject_person_id = null,
		public ?int $event_post_id = null,
		public bool $archived = false
	) {
		if ( $organization_id < 1 || $id < 1 || ! in_array( $type, array( 'person', 'registration', 'event', 'form', 'delegation', 'organization', 'audit' ), true ) ) {
			throw new InvalidArgumentException( 'Invalid policy resource.' );
		}
	}
}
