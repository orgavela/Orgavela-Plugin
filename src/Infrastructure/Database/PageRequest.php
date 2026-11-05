<?php
/**
 * Bounded keyset query request.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use InvalidArgumentException;
/** Internal cursors use BIGINT; API cursor encoding belongs to the application layer. */
final readonly class PageRequest {
	/**
	 * Construct a bounded request with an explicit sort allowlist.
	 *
	 * @param int    $limit Page size, max 100.
	 * @param int    $after Last internal ID (zero starts a page sequence).
	 * @param string $sort Only the indexed ID sort is supported by this base contract.
	 * @param string $direction ASC or DESC.
	 * @throws InvalidArgumentException On unsupported pagination or ordering.
	 */
	public function __construct( public int $limit = 50, public int $after = 0, public string $sort = 'id', public string $direction = 'ASC' ) {
		if ( $limit < 1 || $limit > 100 || $after < 0 || 'id' !== $sort || ! in_array( $direction, array( 'ASC', 'DESC' ), true ) ) {
			throw new InvalidArgumentException( 'Invalid pagination or sort request.' );
		}
	}
}
