<?php
/**
 * Immutable and minimal extension hook event.
 *
 * @package UOP
 */

namespace UOP\Application\Event;

/** Exposes no internal database IDs, profile values or secret evidence. */
final readonly class DomainEventDto {
	/**
	 * Construct post-commit event evidence.
	 *
	 * @param string $event_uuid Public event UUID.
	 * @param string $event_name Stable event name.
	 * @param string $public_id  Object public UUID.
	 */
	public function __construct( public string $event_uuid, public string $event_name, public string $public_id ) {}
}
