<?php
/**
 * Read-only Guardian quota queue health state regressions.
 *
 * @package UOP
 */

namespace UOP\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use UOP\Infrastructure\Queue\GuardianQuotaScheduleHealth;

/** A green status must require a recent completed action and a timely next one. */
final class GuardianQuotaScheduleHealthTest extends TestCase {
	/** Missing or duplicate jobs are distinct actionable warnings. */
	public function test_missing_and_duplicate_pending_jobs_are_not_healthy(): void {
		$now = 1780000000;
		self::assertSame( 'missing', GuardianQuotaScheduleHealth::classify( $now, array(), false, true ) );
		self::assertSame( 'duplicate', GuardianQuotaScheduleHealth::classify( $now, array( $now + 60, $now + 180 ), false, true ) );
		self::assertSame( 'failed', GuardianQuotaScheduleHealth::classify( $now, array(), true, true ) );
	}

	/** Failure, aged-out cron or a misconfigured future gap must not be green. */
	public function test_failed_overdue_and_misconfigured_queue_never_look_healthy(): void {
		$now = 1780000000;
		self::assertSame( 'failed', GuardianQuotaScheduleHealth::classify( $now, array( $now + 300 ), true, true ) );
		self::assertSame( 'overdue', GuardianQuotaScheduleHealth::classify( $now, array( $now - 7201 ), false, true ) );
		self::assertSame( 'gap', GuardianQuotaScheduleHealth::classify( $now, array( $now + 7201 ), false, true ) );
		self::assertSame( 'unavailable', GuardianQuotaScheduleHealth::classify( $now, array( 0 ), false, true ) );
	}

	/** A new installation is visibly pending, not silently reported as healthy. */
	public function test_first_run_is_distinct_from_completed_normal_schedule(): void {
		$now = 1780000000;
		self::assertSame( 'first_run', GuardianQuotaScheduleHealth::classify( $now, array( $now + 3600 ), false, false ) );
		self::assertSame( 'ok', GuardianQuotaScheduleHealth::classify( $now, array( $now + 3600 ), false, true ) );
		self::assertSame( 'ok', GuardianQuotaScheduleHealth::classify( $now, array( $now - 3600 ), false, true ) );
	}

	/** Never manufacture a healthy state from an invalid clock. */
	public function test_invalid_clock_is_explicitly_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		GuardianQuotaScheduleHealth::classify( 0, array( 1 ), false, true );
	}
}
