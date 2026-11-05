<?php
/**
 * Immutable field-policy input.
 *
 * @package UOP
 */

namespace UOP\Application\Policy;

use InvalidArgumentException;

/** Classification and self/delegate controls for one field. */
final readonly class FieldDefinition {
	/**
	 * Initialize required dependencies and validated values.
	 *
	 * @param string $key key input.
	 * @param string $sensitivity sensitivity input.
	 * @param bool   $subject_view subject view input.
	 * @param bool   $subject_edit subject edit input.
	 * @param bool   $delegate_view delegate view input.
	 * @param bool   $delegate_edit delegate edit input.
	 * @throws \InvalidArgumentException When input violates invariants.
	 */
	public function __construct(
		public string $key,
		public string $sensitivity,
		public bool $subject_view,
		public bool $subject_edit,
		public bool $delegate_view,
		public bool $delegate_edit
	) {
		if ( '' === $key || ! preg_match( '/^[a-z][a-z0-9_]*$/D', $key ) ) {
			throw new InvalidArgumentException( 'Invalid field key.' );
		}
	}
}
