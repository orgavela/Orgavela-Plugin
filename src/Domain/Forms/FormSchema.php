<?php
/**
 * Fixed V1 form draft schema and field validation.
 *
 * @package UOP
 */

namespace UOP\Domain\Forms;

use InvalidArgumentException;
use UOP\Core\PublicId;
use UOP\Domain\Conditions\ConditionEngine;
use UOP\Domain\Profiles\FieldRules;

/** A form schema is an allowlisted declarative value; never executable markup. */
final class FormSchema {
	/**
	 * Validate author-input draft without allowing server-owned binding fields.
	 *
	 * @param array<string, mixed> $schema Strict form draft.
	 * @throws InvalidArgumentException For unknown fields or malformed conditions.
	 */
	public function validate_draft( array $schema ): void {
		if ( count( $schema ) !== 2 || ! array_key_exists( 'schema_version', $schema ) || ! array_key_exists( 'fields', $schema ) || 1 !== $schema['schema_version']
			|| ! is_array( $schema['fields'] ) || ! array_is_list( $schema['fields'] )
			|| ! $schema['fields'] || count( $schema['fields'] ) > 100 ) {
			throw new InvalidArgumentException( 'Invalid V1 form draft envelope.' );
		}
		$keys   = array();
		$engine = new ConditionEngine();
		foreach ( $schema['fields'] as $field ) {
			if ( ! is_array( $field ) || array_diff( array_keys( $field ), array( 'key', 'type', 'label', 'required', 'options', 'visible_when', 'consent_definition_public_id' ) )
				|| ! is_bool( $field['required'] ?? null ) ) {
				throw new InvalidArgumentException( 'Form field properties are not allowed.' );
			}
			$definition = array_intersect_key( $field, array_flip( array( 'key', 'type', 'label', 'options' ) ) );
			FieldRules::validate( $definition );
			$key = (string) $field['key'];
			if ( isset( $keys[ $key ] ) ) {
				throw new InvalidArgumentException( 'Duplicate field key.' );
			}
			$keys[ $key ] = true;
			if ( isset( $field['visible_when'] ) ) {
				if ( ! is_array( $field['visible_when'] ) ) {
					throw new InvalidArgumentException( 'Invalid conditional visibility AST.' );
				}
				$engine->validate( $field['visible_when'] );
			}
			if ( 'consent' === $field['type'] ) {
				if ( empty( $field['consent_definition_public_id'] ) || ! is_string( $field['consent_definition_public_id'] ) ) {
					throw new InvalidArgumentException( 'Consent field requires a stable definition reference.' );
				}
				PublicId::from_string( $field['consent_definition_public_id'] );
			} elseif ( isset( $field['consent_definition_public_id'] ) ) {
				throw new InvalidArgumentException( 'Only consent fields may reference consent definitions.' );
			}
		}
		$this->validate_references( $schema, $keys );
	}
	/**
	 * Validate that registration conditions reference existing, non-consent
	 * fields and that conditional visibility has no cycles.
	 *
	 * @param array<string, mixed> $schema Validated form draft.
	 * @param array<string, bool>  $keys   Form field identities.
	 * @throws InvalidArgumentException For a missing reference or cyclic graph.
	 */
	private function validate_references( array $schema, array $keys ): void {
		$types = array();
		foreach ( $schema['fields'] as $field ) {
			$types[ $field['key'] ] = $field['type'];
		}
		$edges = array();
		foreach ( $schema['fields'] as $field ) {
			$key           = $field['key'];
			$edges[ $key ] = array();
			if ( isset( $field['visible_when'] ) ) {
				$this->collect_references( $field['visible_when'], $edges[ $key ] );
			}
			foreach ( $edges[ $key ] as $reference ) {
				if ( ! isset( $keys[ $reference ] ) || 'consent' === $types[ $reference ] ) {
					throw new InvalidArgumentException( 'Condition references an unknown or consent form field.' );
				}
			}
		}
		$visiting = array();
		$visited  = array();
		foreach ( array_keys( $edges ) as $key ) {
			$this->check_cycle( $key, $edges, $visiting, $visited );
		}
	}

	/**
	 * Traverse a validated condition AST and extract registration field keys.
	 *
	 * @param array<string, mixed> $node       AST root, group or predicate.
	 * @param array                $references Accumulated field references.
	 * @phpstan-param list<string> $references
	 */
	private function collect_references( array $node, array &$references ): void {
		foreach ( array( 'all', 'any' ) as $group ) {
			if ( isset( $node[ $group ] ) ) {
				foreach ( $node[ $group ] as $child ) {
					$this->collect_references( $child, $references );
				}
				return;
			}
		}
		if ( isset( $node['not'] ) ) {
			$this->collect_references( $node['not'], $references );
			return;
		}
		if ( 'registration' === ( $node['source'] ?? null ) ) {
			$references[] = (string) $node['field'];
		}
	}

	/**
	 * Detect direct and indirect cycles in a bounded dependency graph.
	 *
	 * @param string                      $key      Current field.
	 * @param array<string, list<string>> $edges    Directed field dependencies.
	 * @param array<string, bool>         $visiting Nodes on recursion path.
	 * @param array<string, bool>         $visited  Completed nodes.
	 * @throws InvalidArgumentException When a dependency cycle exists.
	 */
	private function check_cycle( string $key, array $edges, array &$visiting, array &$visited ): void {
		if ( isset( $visited[ $key ] ) ) {
			return;
		}
		if ( isset( $visiting[ $key ] ) ) {
			throw new InvalidArgumentException( 'Circular form condition dependency.' );
		}
		$visiting[ $key ] = true;
		foreach ( $edges[ $key ] as $dependency ) {
			$this->check_cycle( $dependency, $edges, $visiting, $visited );
		}
		unset( $visiting[ $key ] );
		$visited[ $key ] = true;
	}
}
