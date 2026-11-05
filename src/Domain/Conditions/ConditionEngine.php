<?php
/**
 * Bounded condition AST and authoritative server-side evaluation.
 *
 * @package UOP
 */

namespace UOP\Domain\Conditions;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** No runtime code, unknown operators or request-defined paths can execute. */
final class ConditionEngine {
	/** Whitelisted comparison operators. */
	private const OPS = array( 'eq', 'neq', 'in', 'not_in', 'exists', 'empty', 'lt', 'lte', 'gt', 'gte', 'date_before', 'date_after', 'age_lt_at', 'age_gte_at' );

	/**
	 * Validate the complete schema and depth before evaluating anything.
	 *
	 * @param array<string, mixed> $ast Frozen schema-versioned AST.
	 * @throws InvalidArgumentException For invalid nodes, limits or operators.
	 */
	public function validate( array $ast ): void {
		if ( 1 !== ( $ast['schema_version'] ?? null ) || count( $ast ) !== 2 ) {
			throw new InvalidArgumentException( 'Condition schema version must equal one.' );
		}
		$count = 0;
		$node  = $ast;
		unset( $node['schema_version'] );
		$this->validate_node( $node, 0, $count );
	}

	/**
	 * Compute a deterministic boolean from trusted, server-loaded facts.
	 *
	 * @param array<string, mixed> $ast     Validated condition tree.
	 * @param array                $facts Authorized profile/registration values.
	 * @phpstan-param array<string, array<string, mixed>> $facts
	 * @param array                $contexts Date/time context values, UTC ISO-8601.
	 * @phpstan-param array<string, string> $contexts
	 * @return bool
	 */
	public function evaluate( array $ast, array $facts, array $contexts = array() ): bool {
		$this->validate( $ast );
		unset( $ast['schema_version'] );
		return $this->evaluate_node( $ast, $facts, $contexts );
	}

	/**
	 * Reject unknown nested properties, oversized trees and invalid predicates.
	 *
	 * @param array<string, mixed> $node  One AST node.
	 * @param int                  $depth Current nesting.
	 * @param int                  $count Total nodes, passed by reference.
	 * @throws InvalidArgumentException On invalid AST shape.
	 */
	private function validate_node( array $node, int $depth, int &$count ): void {
		++$count;
		if ( $depth > 8 || $count > 100 ) {
			throw new InvalidArgumentException( 'Condition AST is too complex.' );
		}
		foreach ( array( 'all', 'any' ) as $group ) {
			if ( array_key_exists( $group, $node ) ) {
				if ( 1 !== count( $node ) || ! is_array( $node[ $group ] ) || ! array_is_list( $node[ $group ] ) || ! $node[ $group ] || count( $node[ $group ] ) > 25 ) {
					throw new InvalidArgumentException( 'Invalid condition group.' );
				}
				foreach ( $node[ $group ] as $child ) {
					if ( ! is_array( $child ) ) {
						throw new InvalidArgumentException( 'Invalid group child.' );
					}
					$this->validate_node( $child, $depth + 1, $count );
				}
				return;
			}
		}
		if ( array_key_exists( 'not', $node ) ) {
			if ( 1 !== count( $node ) || ! is_array( $node['not'] ) ) {
				throw new InvalidArgumentException( 'Invalid not node.' );
			}
			$this->validate_node( $node['not'], $depth + 1, $count );
			return;
		}
		$allowed = array( 'source', 'field', 'operator', 'value', 'context' );
		if ( array_diff( array_keys( $node ), $allowed ) || ! isset( $node['source'], $node['field'], $node['operator'] )
			|| ! in_array( $node['source'], array( 'profile', 'registration' ), true )
			|| ! is_string( $node['field'] ) || ! preg_match( '/^[a-z][a-z0-9_]{0,99}$/D', $node['field'] )
			|| ! in_array( $node['operator'], self::OPS, true ) ) {
			throw new InvalidArgumentException( 'Invalid condition predicate.' );
		}
		$op = $node['operator'];
		if ( in_array( $op, array( 'exists', 'empty' ), true ) && array_key_exists( 'value', $node ) ) {
			throw new InvalidArgumentException( 'Existence operators have no value.' );
		}
		if ( in_array( $op, array( 'in', 'not_in' ), true ) && ( ! isset( $node['value'] ) || ! is_array( $node['value'] ) || ! array_is_list( $node['value'] ) || ! $node['value'] || count( $node['value'] ) > 100 ) ) {
			throw new InvalidArgumentException( 'Membership expects a bounded nonempty list.' );
		}
		if ( ! in_array( $op, array( 'exists', 'empty' ), true ) && ! array_key_exists( 'value', $node ) ) {
			throw new InvalidArgumentException( 'Operator requires a value.' );
		}
		if ( array_key_exists( 'value', $node ) ) {
			$json = json_encode( $node['value'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure condition domain has no WordPress runtime dependency.
			if ( false === $json || strlen( $json ) > 1000 || is_object( $node['value'] ) ) {
				throw new InvalidArgumentException( 'Condition value is not a supported literal.' );
			}
		}
		if ( in_array( $op, array( 'age_lt_at', 'age_gte_at' ), true )
			&& ( 'event.start' !== ( $node['context'] ?? null ) || ! is_int( $node['value'] ?? null ) || $node['value'] < 0 || $node['value'] > 150 ) ) {
			throw new InvalidArgumentException( 'Age comparisons require an event start context.' );
		}
		if ( isset( $node['context'] ) && ! in_array( $op, array( 'age_lt_at', 'age_gte_at' ), true ) ) {
			throw new InvalidArgumentException( 'Unexpected condition context.' );
		}
	}

	/**
	 * Evaluate a schema-validated node only.
	 *
	 * @param array<string, mixed> $node AST node.
	 * @param array                $facts Authorized source values.
	 * @phpstan-param array<string, array<string, mixed>> $facts
	 * @param array                $contexts Trusted UTC event dates.
	 * @phpstan-param array<string, string> $contexts
	 * @return bool
	 */
	private function evaluate_node( array $node, array $facts, array $contexts ): bool {
		if ( isset( $node['all'] ) ) {
			foreach ( $node['all'] as $child ) {
				if ( ! $this->evaluate_node( $child, $facts, $contexts ) ) {
					return false;
				}
			}
			return true;
		}
		if ( isset( $node['any'] ) ) {
			foreach ( $node['any'] as $child ) {
				if ( $this->evaluate_node( $child, $facts, $contexts ) ) {
					return true;
				}
			}
			return false;
		}
		if ( isset( $node['not'] ) ) {
			return ! $this->evaluate_node( $node['not'], $facts, $contexts );
		}
		$source = (string) $node['source'];
		$field  = (string) $node['field'];
		$actual = $facts[ $source ][ $field ] ?? null;
		$target = $node['value'] ?? null;
		return match ( $node['operator'] ) {
			'exists' => null !== $actual && '' !== $actual && array() !== $actual,
			'empty' => null === $actual || '' === $actual || array() === $actual,
			'eq' => null !== $actual && $actual === $target,
			'neq' => null !== $actual && $actual !== $target,
			'in' => null !== $actual && in_array( $actual, $target, true ),
			'not_in' => null !== $actual && ! in_array( $actual, $target, true ),
			'lt', 'lte', 'gt', 'gte' => $this->compare( $actual, $target, $node['operator'] ),
			'date_before', 'date_after' => $this->compare_dates( $actual, $target, 'date_before' === $node['operator'] ),
			'age_lt_at', 'age_gte_at' => $this->age_compare( $actual, $target, $contexts['event.start'] ?? '', 'age_lt_at' === $node['operator'] ),
			default => false,
		};
	}

	/**
	 * Compare numeric scalars, rejecting null and nonnumeric values.
	 *
	 * @param mixed  $actual Observed value.
	 * @param mixed  $target Predicate value.
	 * @param string $op     Comparison operation.
	 * @return bool
	 */
	private function compare( mixed $actual, mixed $target, string $op ): bool {
		if ( ! is_numeric( $actual ) || ! is_numeric( $target ) ) {
			return false;
		}
		return match ( $op ) {
			'lt' => (float) $actual < (float) $target,
			'lte' => (float) $actual <= (float) $target,
			'gt' => (float) $actual > (float) $target,
			'gte' => (float) $actual >= (float) $target,
			default => false,
		};
	}

	/**
	 * Compare strict YYYY-MM-DD dates without timezone inference.
	 *
	 * @param mixed $actual First date.
	 * @param mixed $target Second date.
	 * @param bool  $before Earlier flag.
	 * @return bool
	 */
	private function compare_dates( mixed $actual, mixed $target, bool $before ): bool {
		if ( ! $this->date_valid( $actual ) || ! $this->date_valid( $target ) ) {
			return false;
		}
		return $before ? $actual < $target : $actual > $target;
	}

	/**
	 * Compare a date of birth to age at a trusted event instant.
	 *
	 * @param mixed  $dob      Person date of birth.
	 * @param mixed  $target   Integer age threshold.
	 * @param string $event_at UTC event start.
	 * @param bool   $under    Compare strictly under threshold.
	 * @return bool
	 */
	private function age_compare( mixed $dob, mixed $target, string $event_at, bool $under ): bool {
		if ( ! $this->date_valid( $dob ) || ! is_int( $target ) ) {
			return false;
		}
		try {
			$event = new DateTimeImmutable( $event_at, new DateTimeZone( 'UTC' ) );
			$born  = new DateTimeImmutable( $dob . 'T00:00:00+00:00' );
		} catch ( \Exception ) {
			return false;
		}
		if ( $event < $born ) {
			return false;
		}
		$age = $born->diff( $event )->y;
		return $under ? $age < $target : $age >= $target;
	}

	/**
	 * Test an exact ISO civil date.
	 *
	 * @param mixed $date Date-like value.
	 * @return bool
	 */
	private function date_valid( mixed $date ): bool {
		if ( ! is_string( $date ) ) {
			return false;
		}
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new DateTimeZone( 'UTC' ) );
		return false !== $parsed && $parsed->format( 'Y-m-d' ) === $date;
	}
}
