<?php
/**
 * Safe database failure.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use RuntimeException;
/** Carries a database errno; never exposes SQL or values. */
final class DatabaseException extends RuntimeException {}
