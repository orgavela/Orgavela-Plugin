<?php
/**
 * Request correlation identity.
 *
 * @package UOP
 */

namespace UOP\Core;

/** Keeps correlation IDs distinct from object identifiers. */
final readonly class CorrelationId {
	/**
	 * Wrap a validated public ID codec.
	 *
	 * @param PublicId $value Correlation identifier.
	 */
	public function __construct( private PublicId $value ) {}

	/**
	 * Generate a new correlation.
	 *
	 * @return self
	 */
	public static function generate(): self {
		return new self( PublicId::generate() );
	}

	/**
	 * Encode for persistence.
	 *
	 * @return string
	 */
	public function to_binary(): string {
		return $this->value->to_binary();
	}

	/**
	 * Encode for safe external diagnostics.
	 *
	 * @return string
	 */
	public function to_string(): string {
		return $this->value->to_string();
	}
}
