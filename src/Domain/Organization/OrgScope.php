<?php
/**
 * Mandatory organization scope value.
 *
 * @package UOP
 */

namespace UOP\Domain\Organization;

use InvalidArgumentException;
/** Scope is an internal boundary, never a substitute for M2 authorization. */
final readonly class OrgScope {
	/**
	 * Require a concrete persisted organization.
	 *
	 * @param int $id Internal organization key, resolved by the application.
	 * @throws InvalidArgumentException For an absent or invalid organization.
	 */
	public function __construct( public int $id ) {
		if ( $id < 1 ) {
			throw new InvalidArgumentException( 'Organization scope must be positive.' );
		}
	}
}
