<?php
/**
 * UTC production clock.
 *
 * @package UOP
 */

namespace UOP\Core;

use DateTimeImmutable;
use DateTimeZone;
/** Ignores site and server local timezones. */
final class SystemClock implements Clock {
	/**
	 * Read UTC time.
	 *
	 * @return DateTimeImmutable
	 */
	public function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}
}
