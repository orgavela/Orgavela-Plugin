<?php
/**
 * Authoritative, fail-closed object and field policy.
 *
 * @package UOP
 */

namespace UOP\Application\Policy;

use Closure;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AssignmentRepository;
use UOP\Infrastructure\Database\DelegationRepository;
use UOP\Infrastructure\Database\PersonRepository;

/** The same service must guard Admin, REST, Portal, CSV and background jobs. */
final class PolicyService {
	/**
	 * Injected WordPress capability resolver.
	 *
	 * @var Closure(int,string): bool
	 */
	private Closure $has_capability;

	/**
	 * Initialize required dependencies and validated values.
	 *
	 * @param PersonRepository     $people people input.
	 * @param DelegationRepository $delegations delegations input.
	 * @param AssignmentRepository $assignments assignments input.
	 * @param callable             $has_capability has capability input.
	 */
	public function __construct(
		private PersonRepository $people,
		private DelegationRepository $delegations,
		private AssignmentRepository $assignments,
		callable $has_capability
	) {
		$this->has_capability = Closure::fromCallable( $has_capability );
	}

	/**
	 * Map application actions to required primitive capabilities.
	 *
	 * @return array<string, string>
	 */
	private static function capabilities(): array {
		return array(
			'person.create'       => 'uop_edit_people',
			'person.view'         => 'uop_view_people',
			'person.edit'         => 'uop_edit_people',
			'person.link'         => 'uop_manage_organization',
			'organization.manage' => 'uop_manage_organization',
			'delegation.manage'   => 'uop_manage_delegations',
			'event.manage'        => 'uop_manage_events',
			'form.manage'         => 'uop_manage_forms',
			'registration.create' => 'uop_view_registrations',
			'registration.view'   => 'uop_view_registrations',
			'registration.cancel' => 'uop_view_registrations',
			'registration.review' => 'uop_review_registrations',
			'capacity.manage'     => 'uop_manage_capacity',
			'communication.send'  => 'uop_send_communications',
			'export.create'       => 'uop_export_data',
			'privacy.manage'      => 'uop_manage_privacy',
			'audit.view'          => 'uop_view_audit',
		);
	}

	/**
	 * Check actor, object and optional field permissions without caching.
	 *
	 * @param Actor            $actor actor input.
	 * @param string           $action action input.
	 * @param PolicyObject     $domain_object object input.
	 * @param ?FieldDefinition $field field input.
	 * @return Decision
	 */
	public function can( Actor $actor, string $action, PolicyObject $domain_object, ?FieldDefinition $field = null ): Decision {
		$capabilities = self::capabilities();
		if ( ! isset( $capabilities[ $action ] ) ) {
			return Decision::deny( 'DENY_CAPABILITY' );
		}
		if ( $actor->user_id < 1 ) {
			return Decision::deny( 'DENY_UNAUTHENTICATED' );
		}
		if ( $domain_object->archived ) {
			return Decision::deny( 'DENY_ARCHIVED' );
		}
		$scope      = new OrgScope( $domain_object->organization_id );
		$subject_id = $domain_object->subject_person_id ?? ( 'person' === $domain_object->type ? $domain_object->id : null );
		$event_id   = $domain_object->event_post_id ?? ( 'event' === $domain_object->type ? $domain_object->id : 0 );
		$manager    = ( $this->has_capability )( $actor->user_id, 'uop_manage_settings' );
		$mode       = '';
		$ceiling    = null;

		if ( $manager ) {
			$mode    = 'manager';
			$ceiling = 'medical';
		} else {
			$is_subject_action = in_array( $action, array( 'person.view', 'person.edit', 'registration.create', 'registration.view', 'registration.cancel' ), true );
			if ( $is_subject_action && null !== $subject_id ) {
				$self = $this->people->by_user( $scope, $actor->user_id );
				if ( $self && 'active' === $self['status'] && (int) $self['id'] === $subject_id ) {
					$mode = 'self';
				} else {
					$permission = match ( $action ) {
						'person.view', 'registration.view' => 'profile_view',
						'person.edit' => 'profile_edit',
						default => 'registration_manage',
					};
					if ( $this->delegations->allows( $scope, $actor->user_id, $subject_id, $permission, $event_id )
						|| ( 'person.view' === $action && $this->delegations->allows( $scope, $actor->user_id, $subject_id, 'registration_manage', $event_id ) ) ) {
						$mode = 'delegate';
					}
				}
			}
			if ( '' === $mode ) {
				if ( ! ( $this->has_capability )( $actor->user_id, $capabilities[ $action ] ) ) {
					return Decision::deny( 'DENY_CAPABILITY' );
				}
				$role_caps = CapabilityRegistry::roles();
				foreach ( $this->assignments->active_for( $scope, $actor->user_id ) as $assignment ) {
					if ( ! in_array( $capabilities[ $action ], $role_caps[ $assignment['role_key'] ] ?? array(), true ) ) {
						continue;
					}
					if ( 'organization' !== $assignment['scope_type'] || 0 !== (int) $assignment['scope_id'] ) {
						if ( 'event' !== $assignment['scope_type'] || $event_id < 1 || (int) $assignment['scope_id'] !== $event_id ) {
							continue;
						}
					}
					$candidate = (string) $assignment['sensitivity_ceiling'];
					if ( null === $ceiling || self::level( $candidate ) > self::level( $ceiling ) ) {
						$ceiling = $candidate;
					}
					$mode = 'manager';
				}
				if ( '' === $mode ) {
					return Decision::deny( 'DENY_OBJECT_SCOPE' );
				}
			}
		}
		if ( null !== $field ) {
			$level = self::level( $field->sensitivity );
			if ( $level < 0 ) {
				return Decision::deny( 'DENY_FIELD' );
			}
			$editing = in_array( $action, array( 'person.edit', 'person.create' ), true );
			if ( 'self' === $mode && ! ( $editing ? $field->subject_edit : $field->subject_view ) ) {
				return Decision::deny( 'DENY_FIELD' );
			}
			if ( 'delegate' === $mode && ! ( $editing ? $field->delegate_edit : $field->delegate_view ) ) {
				return Decision::deny( 'DENY_FIELD' );
			}
			if ( 'manager' === $mode ) {
				if ( self::level( (string) $ceiling ) < $level ) {
					return Decision::deny( 'DENY_FIELD' );
				}
				if ( $level >= self::level( 'sensitive' ) && ! ( $this->has_capability )( $actor->user_id, $editing ? 'uop_edit_sensitive_data' : 'uop_view_sensitive_data' ) ) {
					return Decision::deny( 'DENY_FIELD' );
				}
			}
		}
		return Decision::allow( 'self' === $mode ? 'ALLOW_SELF' : ( 'delegate' === $mode ? 'ALLOW_DELEGATION' : 'ALLOW_ORG_ASSIGNMENT' ) );
	}

	/**
	 * Map a known sensitivity label to its authorization rank.
	 *
	 * @param string $sensitivity sensitivity input.
	 * @return int
	 */
	private static function level( string $sensitivity ): int {
		$levels = array( 'public', 'internal', 'personal', 'sensitive', 'medical' );
		$level  = array_search( $sensitivity, $levels, true );
		return false === $level ? -1 : (int) $level;
	}
}
