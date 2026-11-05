<?php
/**
 * Domain time seam.
 *
 * @package UOP
 */

namespace UOP\Core;

use DateTimeImmutable;
/** Provides UTC domain time. */
interface Clock {
	/**
	 * Read time in UTC.
	 *
	 * @return DateTimeImmutable
	 */
	public function now(): DateTimeImmutable;
}
