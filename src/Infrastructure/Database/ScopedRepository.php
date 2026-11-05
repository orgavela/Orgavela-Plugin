<?php
/**
 * Query contract for directly organization-owned tables.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use InvalidArgumentException;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
/** Infrastructure rows must pass application policy and projection before becoming public DTOs. */
abstract class ScopedRepository {
	/**
	 * Bind fixed table metadata, never request-derived SQL identifiers.
	 *
	 * @param Connection $db Database.
	 * @param string     $table Full organization-owned table name.
	 * @param array      $columns Explicit selected columns.
	 * @param array      $filter_columns Allowed equality filters.
	 * @phpstan-param non-empty-list<string> $columns
	 * @phpstan-param list<string> $filter_columns
	 * @throws InvalidArgumentException For invalid metadata.
	 */
	public function __construct( private Connection $db, private string $table, private array $columns, private array $filter_columns = array() ) {
		foreach ( array_merge( array( $table ), $columns, $filter_columns ) as $identifier ) {
			if ( ! preg_match( '/^[a-zA-Z0-9_]+$/D', $identifier ) ) {
				throw new InvalidArgumentException( 'Invalid repository identifier.' );
			}
		}
		if ( ! in_array( 'id', $columns, true ) || ! in_array( 'public_id', $columns, true ) || ! in_array( 'organization_id', $columns, true ) || in_array( 'organization_id', $filter_columns, true ) ) {
			throw new InvalidArgumentException( 'Repository identity and organization columns are mandatory; scope cannot be a filter.' );
		}
	}

	/**
	 * Scope the lookup before returning a row to the mapping layer.
	 *
	 * @param OrgScope $scope Trusted application scope.
	 * @param PublicId $id Validated public ID.
	 * @return array<string, mixed>|null
	 */
	final public function find( OrgScope $scope, PublicId $id ): ?array {
		$rows = $this->db->rows(
			'SELECT ' . implode( ', ', array_fill( 0, count( $this->columns ), '%i' ) ) . ' FROM %i WHERE organization_id = %d AND public_id = %s LIMIT 1',
			array_merge( $this->columns, array( $this->table, $scope->id, $id->to_binary() ) )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Query a bounded keyset page; no count-all or unscoped path.
	 *
	 * @param OrgScope    $scope Trusted application scope.
	 * @param PageRequest $page Bounded pagination.
	 * @param array       $filters Equality filters from the allowed field set.
	 * @phpstan-param array<string, int|string> $filters
	 * @return list<array<string, mixed>>
	 * @throws InvalidArgumentException For unknown filters.
	 */
	final public function page( OrgScope $scope, PageRequest $page, array $filters = array() ): array {
		$sql  = 'SELECT ' . implode( ', ', array_fill( 0, count( $this->columns ), '%i' ) ) . ' FROM %i WHERE organization_id = %d';
		$args = array_merge( $this->columns, array( $this->table, $scope->id ) );
		foreach ( $filters as $column => $value ) {
			if ( ! in_array( $column, $this->filter_columns, true ) ) {
				throw new InvalidArgumentException( 'Unknown repository filter.' );
			}
			$sql   .= ' AND %i = %s';
			$args[] = $column;
			$args[] = $value;
		}
		if ( $page->after > 0 ) {
			$sql   .= ' AND id ' . ( 'ASC' === $page->direction ? '>' : '<' ) . ' %d';
			$args[] = $page->after;
		}
		$sql   .= ' ORDER BY id ' . $page->direction . ' LIMIT %d';
		$args[] = $page->limit;
		return $this->db->rows( $sql, $args );
	}
}
