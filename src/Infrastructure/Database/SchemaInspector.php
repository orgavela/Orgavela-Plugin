<?php
/**
 * Physical schema verification.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use RuntimeException;
/** Checks columns, order, defaults, indexes, engine, collation and absence of physical FKs. */
final class SchemaInspector {
	/**
	 * Explicit database context.
	 *
	 * @param Connection     $db Connection.
	 * @param SchemaManifest $manifest Frozen definition.
	 * @param string         $prefix Site table prefix.
	 * @param string         $collation WordPress collation name.
	 */
	public function __construct( private Connection $db, private SchemaManifest $manifest, private string $prefix, private string $collation ) {}

	/**
	 * Verify one complete table.
	 *
	 * @param string $name Manifest key.
	 * @throws RuntimeException On any drift.
	 */
	public function verify_table( string $name ): void {
		$table = $this->prefix . $name;
		$meta  = $this->db->rows( 'SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', array( $table ) );
		if ( 1 !== count( $meta ) || 'InnoDB' !== $meta[0]['ENGINE'] || ( $this->collation && $this->collation !== $meta[0]['TABLE_COLLATION'] ) || ! str_starts_with( (string) $meta[0]['TABLE_COLLATION'], 'utf8mb4_' ) ) {
			throw new RuntimeException( 'Schema engine or collation drift.' );
		}
		$columns = array();
		foreach ( $this->db->rows( 'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s ORDER BY ORDINAL_POSITION', array( $table ) ) as $row ) {
			$default = $row['COLUMN_DEFAULT'];
			// MariaDB can represent SQL NULL as the string NULL in information_schema.
			$default                        = null === $default || 'NULL' === $default ? null : trim( (string) $default, "'" );
			$columns[ $row['COLUMN_NAME'] ] = array(
				'type'     => SchemaManifest::normalize_type( (string) $row['COLUMN_TYPE'] ),
				'nullable' => 'YES' === $row['IS_NULLABLE'],
				'default'  => $default,
				'auto'     => str_contains( (string) $row['EXTRA'], 'auto_increment' ),
			);
		}
		$indexes = array();
		foreach ( $this->db->rows( 'SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s ORDER BY INDEX_NAME, SEQ_IN_INDEX', array( $table ) ) as $row ) {
			if ( null !== $row['SUB_PART'] ) {
				throw new RuntimeException( 'Unexpected index prefix length.' );
			}
			$key                          = (string) $row['INDEX_NAME'];
			$indexes[ $key ]['unique']    = 0 === (int) $row['NON_UNIQUE'];
			$indexes[ $key ]['columns'][] = (string) $row['COLUMN_NAME'];
		}
		ksort( $indexes );
		$expected = $this->manifest->expected( $name );
		if ( $columns !== $expected['columns'] || $indexes !== $expected['indexes'] ) {
			throw new RuntimeException( 'Schema column or index drift.' );
		}
		$fks = $this->db->rows( 'SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = %s', array( $table ) );
		if ( $fks ) {
			throw new RuntimeException( 'Physical foreign keys are not part of V1.' );
		}
	}

	/**
	 * Verify all tables and reject unexpected UOP tables.
	 *
	 * @throws RuntimeException On unexpected tables.
	 */
	public function verify_all(): void {
		foreach ( array_keys( $this->manifest->tables() ) as $name ) {
			$this->verify_table( $name );
		}
		$actual = $this->db->rows( 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND LEFT(TABLE_NAME, %d) = %s', array( strlen( $this->prefix ), $this->prefix ) );
		if ( count( $actual ) !== count( $this->manifest->tables() ) ) {
			throw new RuntimeException( 'Unexpected UOP table set.' );
		}
	}
}
