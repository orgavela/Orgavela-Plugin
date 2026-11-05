<?php
/**
 * Internal policy result.
 *
 * @package UOP
 */

namespace UOP\Application\Policy;

/** Reason codes must never disclose existence of a hidden object to the client. */
final readonly class Decision {
	/**
	 * Initialize required dependencies and validated values.
	 *
	 * @param bool   $allowed allowed input.
	 * @param string $reason reason input.
	 */
	public function __construct( public bool $allowed, public string $reason ) {}
	/**
	 * Construct an allowed policy decision.
	 *
	 * @param string $reason reason input.
	 * @return self
	 */
	public static function allow( string $reason ): self {
		return new self( true, $reason );
	}
	/**
	 * Construct a denied policy decision.
	 *
	 * @param string $reason reason input.
	 * @return self
	 */
	public static function deny( string $reason ): self {
		return new self( false, $reason );
	}
}
