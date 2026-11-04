<?php
/**
 * P4-02 isolated, not-connected domain prototype for independent consent lanes.
 *
 * IMPORTANT: All input MUST be loaded and authorized by a trusted server-side
 * application service. Never accept 'authorized', 'current', or guardian flags
 * from HTTP/REST/form data. This helper alone grants no WordPress permission.
 *
 * @package UOP
 */

namespace UOP\Application\Consent;

use InvalidArgumentException;

/**
 * Fail-closed result for one version-pinned subject/registration/consent policy.
 *
 * @internal P4-02 prototype pending accepted ADR, migration, P4-03 and E2E.
 */
final class ConsentRequirementGate {
	private const ROLES       = array( 'self', 'representative' );
	private const MODES       = array( 'self', 'representative', 'both' );
	private const SCOPE_KEYS  = array( 'organization_id', 'event_post_id', 'registration_id', 'subject_person_id', 'definition_version_id' );
	private const RECORD_KEYS = array( 'organization_id', 'event_post_id', 'registration_id', 'subject_person_id', 'definition_version_id', 'role', 'actor_user_id', 'decision', 'current', 'authorized', 'guardian_verified' );

	/**
	 * Evaluate only canonical server-loaded and already policy-checked facts.
	 *
	 * The caller must load *one current candidate per role*, never client data
	 * or the whole historical list. Duplicate role slots fail closed.
	 *
	 * @param string $mode   Frozen requirement: self, representative or both.
	 * @param array  $scope  Trusted immutable event/registration/consent scope.
	 * @phpstan-param array<string,mixed> $scope
	 * @param array  $records Exactly current role candidates, from repository.
	 * @phpstan-param list<array<string,mixed>> $records
	 * @return bool Whether all requested role lanes are independently effective.
	 *
	 * @throws InvalidArgumentException On unknown/malformed caller data.
	 */
	public function satisfied( string $mode, array $scope, array $records ): bool {
		if ( ! in_array( $mode, self::MODES, true ) ) {
			throw new InvalidArgumentException( 'Unknown consent requirement.' );
		}
		self::validate_keys( $scope, self::SCOPE_KEYS );
		foreach ( self::SCOPE_KEYS as $key ) {
			if ( ! is_int( $scope[ $key ] ) || $scope[ $key ] < 1 ) {
				throw new InvalidArgumentException( 'Untrusted consent scope.' );
			}
		}
		if ( ! array_is_list( $records ) || count( $records ) > 2 ) {
			throw new InvalidArgumentException( 'Invalid consent role candidate set.' );
		}
		$roles = array();
		foreach ( $records as $record ) {
			if ( ! is_array( $record ) ) {
				throw new InvalidArgumentException( 'Invalid consent decision fact.' );
			}
			self::validate_keys( $record, self::RECORD_KEYS );
			$role = $record['role'];
			if ( ! in_array( $role, self::ROLES, true )
				|| ! is_int( $record['actor_user_id'] ) || $record['actor_user_id'] < 1
				|| ! in_array( $record['decision'], array( 'granted', 'denied', 'withdrawn' ), true )
				|| ! is_bool( $record['current'] ) || ! is_bool( $record['authorized'] )
				|| ! is_bool( $record['guardian_verified'] ) ) {
				throw new InvalidArgumentException( 'Invalid consent role identity or decision.' );
			}
			foreach ( self::SCOPE_KEYS as $key ) {
				if ( ! is_int( $record[ $key ] ) || $scope[ $key ] !== $record[ $key ] ) {
					return false;
				}
			}
			// A caller must resolve concurrent records/withdrawals under its
			// transaction, not pick one here by insertion order.
			if ( isset( $roles[ $role ] ) ) {
				return false;
			}
			$roles[ $role ] = array(
				'actor' => $record['actor_user_id'],
				'valid' => 'granted' === $record['decision'] && true === $record['current']
					&& true === $record['authorized']
					&& ( 'self' === $role || true === $record['guardian_verified'] ),
			);
		}
		$own            = $roles['self'] ?? null;
		$representative = $roles['representative'] ?? null;
		if ( 'self' === $mode ) {
			return null !== $own && true === $own['valid'];
		}
		if ( 'representative' === $mode ) {
			return null !== $representative && true === $representative['valid'];
		}
		return null !== $own && null !== $representative
			&& true === $own['valid'] && true === $representative['valid']
			&& $own['actor'] !== $representative['actor'];
	}

	/**
	 * Strictly require only the trusted allowlisted tuple columns.
	 *
	 * @param array<string,mixed> $value Input.
	 * @param list<string>        $keys  Required keys.
	 * @throws InvalidArgumentException When any input field is extra/missing.
	 */
	private static function validate_keys( array $value, array $keys ): void {
		if ( count( $value ) !== count( $keys ) || array_diff( $keys, array_keys( $value ) ) || array_diff( array_keys( $value ), $keys ) ) {
			throw new InvalidArgumentException( 'Invalid consent fact shape.' );
		}
	}
}
