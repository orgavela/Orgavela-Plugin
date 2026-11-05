<?php
/**
 * Immutable ordered migration registry.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use InvalidArgumentException;
/** Rejects duplicate or missing structural versions. */
final class MigrationRegistry {
	/**
	 * Ordered migrations.
	 *
	 * @var list<Migration>
	 */
	private array $migrations;

	/**
	 * Validate the complete migration chain.
	 *
	 * @param array $migrations Ordered migrations.
	 * @phpstan-param list<Migration> $migrations
	 * @throws InvalidArgumentException For missing or duplicate versions.
	 */
	public function __construct( array $migrations ) {
		foreach ( $migrations as $index => $migration ) {
			if ( $migration->version() !== $index + 1 ) {
				throw new InvalidArgumentException( 'Migration versions must be consecutive and unique.' );
			}
		}
		$this->migrations = $migrations;
	}

	/**
	 * Select pending migrations.
	 *
	 * @param int $installed Installed version.
	 * @return list<Migration>
	 */
	public function pending( int $installed ): array {
		return array_values( array_filter( $this->migrations, static fn ( Migration $migration ): bool => $migration->version() > $installed ) );
	}

	/**
	 * Get latest structural version.
	 *
	 * @return int
	 */
	public function latest(): int {
		return count( $this->migrations );
	}
}
