<?php
/**
 * Bounded maintenance of expired pseudonymous Guardian claim counters.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use InvalidArgumentException;

/** Site-local maintenance is separate from tenant-bound quota repositories. */
final class GuardianClaimQuotaPruner {
	private const WINDOW_SECONDS = 86400;

	/**
	 * Bind the connection and trusted WordPress options table.
	 *
	 * @param Connection $db            Prepared SQL adapter.
	 * @param string     $options_table Trusted site-local wp_options table.
	 * @throws InvalidArgumentException For invalid table identity.
	 */
	public function __construct( private Connection $db, private string $options_table ) {
		if ( ! preg_match( '/^[A-Za-z0-9_]+options$/D', $options_table ) ) {
			throw new InvalidArgumentException( 'Invalid guardian quota storage.' );
		}
	}

	/**
	 * Remove at most 100 valid options expired beyond a further 24h grace.
	 * Active, corrupt, and unrelated records are not deleted.
	 * A single conditional InnoDB DELETE rechecks concurrent row updates.
	 *
	 * @param int $now Trusted server Unix timestamp.
	 * @return int Removed rows, bounded by 100.
	 * @throws \Exception On invalid clock or database failure.
	 */
	public function prune_expired( int $now ): int {
		if ( $now < self::WINDOW_SECONDS + 1 ) {
			throw new InvalidArgumentException( 'Invalid guardian quota prune clock.' );
		}
		$prefix = 'uop_gclaim_q2_';
		return $this->db->execute(
			"DELETE FROM %i WHERE option_name LIKE %s AND LEFT(option_name, 14) = %s AND option_value REGEXP %s AND CAST(SUBSTRING_INDEX(option_value, ':', -1) AS UNSIGNED) <= %d LIMIT 100",
			array( $this->options_table, $prefix . '%', $prefix, '^[0-3]:[0-9]{1,12}$', $now - self::WINDOW_SECONDS )
		);
	}
}
