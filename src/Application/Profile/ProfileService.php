<?php
/**
 * Organization-scoped profile authoring and authorized value updates.
 *
 * @package UOP
 */

namespace UOP\Application\Profile;

use RuntimeException;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\FieldDefinition;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Domain\Profiles\FieldRules;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\ProfileFieldRepository;
use UOP\Infrastructure\Database\ProfileValueRepository;

/** Does not allow field metadata, subject IDs or status via mass assignment. */
final class ProfileService {
	/**
	 * Inject the existing M2 permission and transaction boundary.
	 *
	 * @param ProfileFieldRepository $fields   Profile definitions.
	 * @param ProfileValueRepository $values   Typed value persistence.
	 * @param PersonRepository       $people   Person scope lookup.
	 * @param PolicyService          $policy   Central object and field policy.
	 * @param TransactionManager     $tx       Transaction manager.
	 * @param AuditWriter            $audit    Durable diagnostics.
	 * @param OutboxRepository       $outbox   Transactional domain event.
	 */
	public function __construct(
		private ProfileFieldRepository $fields,
		private ProfileValueRepository $values,
		private PersonRepository $people,
		private PolicyService $policy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox
	) {}

	/**
	 * Define an organization-owned field after manager authorization.
	 *
	 * @param Actor                $actor       Current manager.
	 * @param OrgScope             $scope       Trusted organization.
	 * @param array<string, mixed> $definition  Strict typed author DTO.
	 * @param string               $utc_now     UTC timestamp.
	 * @param CorrelationId        $correlation Trace identity.
	 * @return PublicId
	 * @throws RuntimeException When unauthorized.
	 */
	public function define( Actor $actor, OrgScope $scope, array $definition, string $utc_now, CorrelationId $correlation ): PublicId {
		FieldRules::validate( $definition );
		$object = new PolicyObject( $scope->id, 'organization', $scope->id );
		if ( ! $this->policy->can( $actor, 'organization.manage', $object )->allowed ) {
			throw new RuntimeException( 'Profile definition denied.' );
		}
		$uuid = PublicId::generate();
		$this->tx->run(
			function () use ( $actor, $scope, $definition, $utc_now, $correlation, $uuid, $object ): void {
				$this->fields->create( $scope, $uuid, $definition, $utc_now );
				$row = $this->fields->find( $scope, $uuid );
				if ( ! $row ) {
					throw new RuntimeException( 'Profile field could not be loaded.' );
				}
				$event = PublicId::generate();
				$this->audit->append( $scope, $actor, 'profile.field_created', $object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'profile_field', (int) $row['id'], 'profile.field_created', $correlation, array( 'public_id' => $uuid->to_string() ) );
			}
		);
		return $uuid;
	}

	/**
	 * Replace one user-editable field, preserving null/false/empty distinctions.
	 *
	 * @param Actor         $actor       Current actor.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param PublicId      $person_id   Target person UUID.
	 * @param PublicId      $field_id    Field UUID.
	 * @param mixed         $input       Typed field input.
	 * @param string        $utc_now     UTC timestamp.
	 * @param CorrelationId $correlation Correlation ID.
	 * @throws RuntimeException When no scoped access exists.
	 */
	public function replace_value( Actor $actor, OrgScope $scope, PublicId $person_id, PublicId $field_id, mixed $input, string $utc_now, CorrelationId $correlation ): void {
		$this->tx->run(
			function () use ( $actor, $scope, $person_id, $field_id, $input, $utc_now, $correlation ): void {
				$person = $this->people->find( $scope, $person_id );
				$field  = $this->fields->find( $scope, $field_id );
				if ( ! $person || ! $field || 'active' !== $field['status'] ) {
					throw new RuntimeException( 'Profile field unavailable.' );
				}
				$policy = new FieldDefinition( (string) $field['field_key'], (string) $field['sensitivity'], (bool) $field['subject_view'], (bool) $field['subject_edit'], (bool) $field['delegate_view'], (bool) $field['delegate_edit'] );
				$object = new PolicyObject( $scope->id, 'person', (int) $person['id'] );
				if ( ! $this->policy->can( $actor, 'person.edit', $object, $policy )->allowed ) {
					throw new RuntimeException( 'Profile field edit denied.' );
				}
				$settings = json_decode( (string) $field['settings_json'], true, 32, JSON_THROW_ON_ERROR );
				$values   = FieldRules::normalize( (string) $field['data_type'], $input, $settings['options'] ?? array() );
				$this->values->replace( $scope, (int) $person['id'], (int) $field['id'], $values, $utc_now );
				$event = PublicId::generate();
				$this->audit->append( $scope, $actor, 'profile.value_changed', $object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'person', (int) $person['id'], 'profile.value_changed', $correlation, array( 'public_id' => $person_id->to_string() ) );
			}
		);
	}
}
