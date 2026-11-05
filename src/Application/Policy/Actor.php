<?php
/**
 * Authenticated WordPress actor identity.
 *
 * @package UOP
 */

namespace UOP\Application\Policy;

/** No role names and no cached permissions are stored here. */
final readonly class Actor {
	/**
	 * Initialize required dependencies and validated values.
	 *
	 * @param int $user_id user id input.
	 */
	public function __construct( public int $user_id ) {}
}
