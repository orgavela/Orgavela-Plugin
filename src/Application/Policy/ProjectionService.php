<?php
/**
 * Single projection boundary for all response channels.
 *
 * @package UOP
 */

namespace UOP\Application\Policy;

use UOP\Core\PublicId;

/** Reject object access before any value is serialized or rendered. */
final class ProjectionService {
	/**
	 * Initialize required dependencies and validated values.
	 *
	 * @param PolicyService $policy policy input.
	 */
	public function __construct( private PolicyService $policy ) {}

	/**
	 * Filter all fields using one authoritative projection boundary.
	 *
	 * @param Actor        $actor actor input.
	 * @param string       $action action input.
	 * @param PolicyObject $domain_object object input.
	 * @param PublicId     $public_id public id input.
	 * @param array        $definitions definitions input.
	 * @param array        $values values input.
	 * @return array<string, mixed>|null Null is a hidden object.
	 * @phpstan-param array<string, FieldDefinition> $definitions
	 * @phpstan-param array<string, mixed> $values
	 */
	public function project( Actor $actor, string $action, PolicyObject $domain_object, PublicId $public_id, array $definitions, array $values ): ?array {
		if ( ! $this->policy->can( $actor, $action, $domain_object )->allowed ) {
			return null;
		}
		$visible = array();
		foreach ( $definitions as $key => $definition ) {
			if ( $key !== $definition->key || ! array_key_exists( $key, $values ) ) {
				continue;
			}
			if ( $this->policy->can( $actor, $action, $domain_object, $definition )->allowed ) {
				$visible[ $key ] = $values[ $key ];
			}
		}
		return array(
			'public_id' => $public_id->to_string(),
			'fields'    => $visible,
		);
	}
}
