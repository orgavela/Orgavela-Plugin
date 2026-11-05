<?php
/**
 * Opaque public identifiers.
 *
 * @package UOP
 */

namespace UOP\Core;

use InvalidArgumentException;

/** Cryptographically random RFC 4122 variant UUID v4. */
final readonly class PublicId {
	/**
	 * Keep validated binary representation private.
	 *
	 * @param string $bytes UUID bytes.
	 */
	private function __construct( private string $bytes ) {}

	/**
	 * Generate a new identifier.
	 *
	 * @return self
	 */
	public static function generate(): self {
		$bytes    = random_bytes( 16 );
		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );
		return new self( $bytes );
	}

	/**
	 * Decode the canonical external form before repository access.
	 *
	 * @param string $uuid Lowercase canonical UUID.
	 * @return self
	 * @throws InvalidArgumentException For malformed or non-v4 identifiers.
	 */
	public static function from_string( string $uuid ): self {
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $uuid ) ) {
			throw new InvalidArgumentException( 'Invalid canonical UUID v4.' );
		}
		return new self( (string) hex2bin( str_replace( '-', '', $uuid ) ) );
	}

	/**
	 * Decode database bytes, validating variant and version too.
	 *
	 * @param string $bytes Database bytes.
	 * @return self
	 * @throws InvalidArgumentException For invalid bytes.
	 */
	public static function from_binary( string $bytes ): self {
		if ( 16 !== strlen( $bytes ) ) {
			throw new InvalidArgumentException( 'Invalid UUID byte length.' );
		}
		$id = new self( $bytes );
		return self::from_string( $id->to_string() );
	}

	/**
	 * Encode the persistence form.
	 *
	 * @return string
	 */
	public function to_binary(): string {
		return $this->bytes;
	}

	/**
	 * Encode canonical external form.
	 *
	 * @return string
	 */
	public function to_string(): string {
		$hex = bin2hex( $this->bytes );
		return substr( $hex, 0, 8 ) . '-' . substr( $hex, 8, 4 ) . '-' . substr( $hex, 12, 4 ) . '-' . substr( $hex, 16, 4 ) . '-' . substr( $hex, 20 );
	}
}
