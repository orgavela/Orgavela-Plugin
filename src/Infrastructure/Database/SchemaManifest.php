<?php
/**
 * Frozen schema definitions.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use RuntimeException;
/** The 26 Phase 2 tables plus the two Phase 3 additions. */
final class SchemaManifest {
	/**
	 * Validated definitions.
	 *
	 * @var array<string, list<string>>
	 */
	private array $tables;

	/**
	 * Load the reviewed build artifact.
	 *
	 * @param string $path Manifest path.
	 * @throws RuntimeException For invalid schema artifacts.
	 */
	public function __construct( string $path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local immutable plugin artifact, not an HTTP request.
		$manifest = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
		if ( 1 !== $manifest['schema_version'] || 28 !== count( $manifest['tables'] ) ) {
			throw new RuntimeException( 'Invalid frozen schema manifest.' );
		}
		$this->tables = $manifest['tables'];
	}

	/**
	 * Return table definitions, preserving declaration order.
	 *
	 * @return array<string, list<string>>
	 */
	public function tables(): array {
		return $this->tables;
	}

	/**
	 * Emit dbDelta-compatible SQL using WordPress charset/collation.
	 *
	 * @param string $name Manifest key.
	 * @param string $prefix Site-specific prefix.
	 * @param string $collation WordPress charset/collation clause.
	 * @return string
	 * @throws RuntimeException On unknown table or invalid identifier.
	 */
	public function ddl( string $name, string $prefix, string $collation ): string {
		if ( ! isset( $this->tables[ $name ] ) || ! preg_match( '/^[a-zA-Z0-9_]+$/D', $prefix . $name ) ) {
			throw new RuntimeException( 'Invalid schema table identifier.' );
		}
		$lines = str_replace( 'PRIMARY KEY (', 'PRIMARY KEY  (', $this->tables[ $name ] );
		return 'CREATE TABLE ' . $prefix . $name . " (\n" . implode( "\n", $lines ) . "\n) ENGINE=InnoDB " . $collation . ';';
	}

	/**
	 * Extract comparable column and index metadata.
	 *
	 * @param string $name Manifest key.
	 * @return array{columns: array<string, array{type: string, nullable: bool, default: ?string, auto: bool}>, indexes: array<string, array{unique: bool, columns: list<string>}>}
	 */
	public function expected( string $name ): array {
		$columns = array();
		$indexes = array();
		foreach ( $this->tables[ $name ] as $line ) {
			if ( preg_match( '/^(PRIMARY KEY|UNIQUE KEY|KEY)\s*(\w+)?\s*\(([^)]+)\)/', $line, $match ) ) {
				$key             = 'PRIMARY KEY' === $match[1] ? 'PRIMARY' : $match[2];
				$indexes[ $key ] = array(
					'unique'  => 'KEY' !== $match[1],
					'columns' => array_map( 'trim', explode( ',', $match[3] ) ),
				);
				continue;
			}
			preg_match( '/^(\w+)\s+([A-Z]+(?:\([0-9,]+\))?)( UNSIGNED)?(.*)/', $line, $match );
			preg_match( "/DEFAULT (?:'([^']*)'|([0-9]+))/", $line, $default );
			$columns[ $match[1] ] = array(
				'type'     => self::normalize_type( $match[2] . ( $match[3] ?? '' ) ),
				'nullable' => ! str_contains( $line, 'NOT NULL' ),
				'default'  => $default ? ( $default[2] ?? $default[1] ) : null,
				'auto'     => str_contains( $line, 'AUTO_INCREMENT' ),
			);
		}
		ksort( $indexes );
		return array(
			'columns' => $columns,
			'indexes' => $indexes,
		);
	}

	/**
	 * Ignore integer display widths, which differ between supported engines.
	 *
	 * @param string $type Database type.
	 * @return string
	 */
	public static function normalize_type( string $type ): string {
		return (string) preg_replace( '/\b(tinyint|smallint|int|bigint)\(\d+\)/', '$1', strtolower( trim( $type ) ) );
	}
}
