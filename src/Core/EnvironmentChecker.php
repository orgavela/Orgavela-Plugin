<?php
/**
 * Read-only compatibility gate.
 *
 * @package UOP
 */

namespace UOP\Core;

/** Checks the frozen support floor before any UOP mutation. */
final class EnvironmentChecker {
	/**
	 * Evaluate a captured environment.
	 *
	 * @param string $php PHP version.
	 * @param string $wordpress WordPress version.
	 * @param string $database Database server version.
	 * @param bool   $innodb Transactional engine availability.
	 * @param bool   $utf8mb4 Charset availability.
	 * @return list<string> Stable error codes.
	 */
	public function errors( string $php, string $wordpress, string $database, bool $innodb, bool $utf8mb4 ): array {
		$errors = array();
		if ( version_compare( $php, '8.3', '<' ) ) {
			$errors[] = 'php_minimum';
		}
		if ( version_compare( $wordpress, '6.9', '<' ) ) {
			$errors[] = 'wordpress_minimum';
		}
		$is_maria = false !== stripos( $database, 'MariaDB' );
		$pattern  = $is_maria ? '/(\d+\.\d+\.\d+)-MariaDB/i' : '/^(\d+\.\d+\.\d+)/';
		if ( ! preg_match( $pattern, $database, $matches ) || version_compare( $matches[1], $is_maria ? '10.11' : '8.0', '<' ) ) {
			$errors[] = 'database_minimum';
		}
		if ( ! $innodb ) {
			$errors[] = 'innodb_required';
		}
		if ( ! $utf8mb4 ) {
			$errors[] = 'utf8mb4_required';
		}
		return $errors;
	}
}
