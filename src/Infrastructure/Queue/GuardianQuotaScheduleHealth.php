<?php
/**
 * Read-only health signal for the site-local Guardian quota retention queue.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Queue;

use DateTimeInterface;
use InvalidArgumentException;
use Throwable;

/** Read only scheduler metadata, never queue payloads or personal identities. */
final class GuardianQuotaScheduleHealth {
	private const HOOK      = 'uop_guardian_quota_prune';
	private const GROUP     = 'uop';
	private const MAX_DELAY = 7200;

	/**
	 * Classify only aggregate queue metadata without reading any action payload.
	 *
	 * @param int       $now            Trusted server timestamp.
	 * @param list<int> $next_scheduled Scheduled timestamps, maximum two sampled.
	 * @param bool      $has_failed     Whether a failed action needs review.
	 * @param bool      $has_completed  Whether at least one action has completed.
	 * @return string Fixed diagnostic code with no personal or action identifiers.
	 * @throws InvalidArgumentException For invalid timestamps.
	 */
	public static function classify( int $now, array $next_scheduled, bool $has_failed, bool $has_completed ): string {
		if ( $now < 1 ) {
			throw new InvalidArgumentException( 'Guardian schedule diagnostic clock invalid.' );
		}
		if ( $has_failed ) {
			return 'failed';
		}
		if ( 0 === count( $next_scheduled ) ) {
			return 'missing';
		}
		if ( count( $next_scheduled ) > 1 ) {
			return 'duplicate';
		}
		$next = $next_scheduled[0];
		if ( ! is_int( $next ) || $next < 1 ) {
			return 'unavailable';
		}
		if ( $next < $now - self::MAX_DELAY ) {
			return 'overdue';
		}
		if ( $next > $now + self::MAX_DELAY ) {
			return 'gap';
		}
		return $has_completed ? 'ok' : 'first_run';
	}

	/**
	 * Inspect the current Action Scheduler store without mutating any action.
	 * This is not proof that an external cron has been configured or runs.
	 *
	 * @param int $now Trusted server timestamp.
	 * @return string Fixed, non-identifying diagnostic status.
	 */
	public static function inspect( int $now ): string {
		if ( $now < 1
			|| ! function_exists( 'as_get_scheduled_actions' )
			|| ! class_exists( '\ActionScheduler' ) ) {
			return 'unavailable';
		}
		try {
			$filter = array( 'hook' => self::HOOK, 'group' => self::GROUP, 'per_page' => 2 );
			$pending = as_get_scheduled_actions( array_merge( $filter, array( 'status' => 'pending' ) ), 'ids' );
			$failed = as_get_scheduled_actions( array_merge( $filter, array( 'status' => 'failed', 'per_page' => 1 ) ), 'ids' );
			$completed = as_get_scheduled_actions( array_merge( $filter, array( 'status' => 'complete', 'per_page' => 1 ) ), 'ids' );
			if ( ! is_array( $pending ) || ! is_array( $failed ) || ! is_array( $completed ) ) {
				return 'unavailable';
			}
			$store = \ActionScheduler::store();
			$times = array();
			foreach ( $pending as $id ) {
				if ( ! is_numeric( $id ) || (int) $id < 1 ) {
					return 'unavailable';
				}
				$date = $store->get_date( (int) $id );
				if ( ! $date instanceof DateTimeInterface ) {
					return 'unavailable';
				}
				$times[] = $date->getTimestamp();
			}
			return self::classify( $now, $times, ! empty( $failed ), ! empty( $completed ) );
		} catch ( Throwable ) {
			// Missing or corrupt scheduler storage must never look healthy.
			return 'unavailable';
		}
	}
}
