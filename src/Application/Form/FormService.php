<?php
/**
 * Authorized form draft and immutable publishing commands.
 *
 * @package UOP
 */

namespace UOP\Application\Form;

use RuntimeException;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Forms\FormSchema;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\FormRepository;
use UOP\Infrastructure\Database\OutboxRepository;

/** Direct editing of published form_versions is intentionally impossible. */
final class FormService {
	/**
	 * Bind the single policy, transaction and evidence pipeline.
	 *
	 * @param FormRepository     $forms Forms and immutable version storage.
	 * @param PolicyService      $policy Authorization boundary.
	 * @param TransactionManager $tx Atomic transaction.
	 * @param AuditWriter        $audit Audit writer.
	 * @param OutboxRepository   $outbox Transactional events.
	 */
	public function __construct( private FormRepository $forms, private PolicyService $policy, private TransactionManager $tx, private AuditWriter $audit, private OutboxRepository $outbox ) {}

	/**
	 * Create an editable form without accepting protected root attributes.
	 *
	 * @param Actor               $actor Request actor.
	 * @param OrgScope            $scope Trusted organization.
	 * @param string              $key Form key.
	 * @param string              $title Form title.
	 * @param string              $context Form domain context.
	 * @param array<string,mixed> $draft Author schema.
	 * @param string              $utc_now UTC timestamp.
	 * @param CorrelationId       $correlation Request trace.
	 * @return PublicId
	 */
	public function create( Actor $actor, OrgScope $scope, string $key, string $title, string $context, array $draft, string $utc_now, CorrelationId $correlation ): PublicId {
		$domain_object = new PolicyObject( $scope->id, 'organization', $scope->id );
		$this->authorize( $actor, $domain_object );
		( new FormSchema() )->validate_draft( $draft );
		$uuid = PublicId::generate();
		$this->tx->run(
			function () use ( $actor, $scope, $key, $title, $context, $draft, $utc_now, $correlation, $uuid, $domain_object ): void {
				$this->authorize( $actor, $domain_object );
				$this->forms->create( $scope, $uuid, $actor->user_id, $key, $title, $context, $draft, $utc_now );
				$root = $this->forms->find( $scope, $uuid );
				if ( ! $root ) {
					throw new RuntimeException( 'New form not found.' );
				}
				$event = PublicId::generate();
				$this->audit->append( $scope, $actor, 'form.created', $domain_object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'form', (int) $root['id'], 'form.created', $correlation, array( 'public_id' => $uuid->to_string() ) );
			}
		);
		return $uuid;
	}

	/**
	 * Update only draft schema with an optimistic revision guard.
	 *
	 * @param Actor               $actor Current editor.
	 * @param OrgScope            $scope Trusted organization.
	 * @param PublicId            $form_id Form public UUID.
	 * @param int                 $expected Expected revision.
	 * @param array<string,mixed> $schema Validated draft DTO.
	 * @param string              $utc_now UTC timestamp.
	 * @param CorrelationId       $correlation Request trace.
	 * @throws RuntimeException For stale or unauthorized update.
	 */
	public function save_draft( Actor $actor, OrgScope $scope, PublicId $form_id, int $expected, array $schema, string $utc_now, CorrelationId $correlation ): void {
		( new FormSchema() )->validate_draft( $schema );
		$this->tx->run(
			function () use ( $actor, $scope, $form_id, $expected, $schema, $utc_now, $correlation ): void {
				$root = $this->forms->find( $scope, $form_id );
				if ( ! $root ) {
					throw new RuntimeException( 'Form unavailable.' );
				}
				$domain_object = new PolicyObject( $scope->id, 'form', (int) $root['id'] );
				$this->authorize( $actor, $domain_object );
				if ( ! $this->forms->save_draft( $scope, $form_id, $expected, $schema, $utc_now ) ) {
					throw new RuntimeException( 'Stale form draft revision.' );
				}
				$event = PublicId::generate();
				$this->audit->append( $scope, $actor, 'form.draft_saved', $domain_object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'form', (int) $root['id'], 'form.draft_saved', $correlation, array( 'public_id' => $form_id->to_string() ) );
			}
		);
	}

	/**
	 * Publish a new immutable snapshot at an exact draft revision.
	 *
	 * @param Actor         $actor Editor.
	 * @param OrgScope      $scope Trusted organization.
	 * @param PublicId      $form_id Form public UUID.
	 * @param int           $expected Expected draft revision.
	 * @param string        $utc_now UTC timestamp.
	 * @param CorrelationId $correlation Request trace.
	 * @return PublicId Published version public UUID.
	 * @throws RuntimeException If stale, unauthorized, archived or consent unresolved.
	 */
	public function publish( Actor $actor, OrgScope $scope, PublicId $form_id, int $expected, string $utc_now, CorrelationId $correlation ): PublicId {
		return $this->tx->run(
			function () use ( $actor, $scope, $form_id, $expected, $utc_now, $correlation ): PublicId {
				$root = $this->forms->lock( $scope, $form_id );
				if ( ! $root || null !== $root['archived_at'] || (int) $root['draft_revision'] !== $expected ) {
					throw new RuntimeException( 'Form unavailable or stale.' );
				}
				$domain_object = new PolicyObject( $scope->id, 'form', (int) $root['id'] );
				$this->authorize( $actor, $domain_object );
				$schema = json_decode( (string) $root['draft_schema_json'], true, 64, JSON_THROW_ON_ERROR );
				( new FormSchema() )->validate_draft( $schema );
				foreach ( $schema['fields'] as &$field ) {
					if ( 'consent' === $field['type'] ) {
						$definition = PublicId::from_string( $field['consent_definition_public_id'] );
						$pinned     = $this->forms->current_consent( $scope, $definition );
						if ( ! $pinned ) {
							throw new RuntimeException( 'Consent definition has no active immutable version.' );
						}
						$field['consent_version_public_id'] = $pinned->to_string();
					}
				}
				unset( $field );
				$version = PublicId::generate();
				$saved   = $this->forms->append_published( $scope, $root, $version, $schema, $actor->user_id, $utc_now );
				$event   = PublicId::generate();
				$this->audit->append( $scope, $actor, 'form.published', $domain_object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'form', (int) $root['id'], 'form.published', $correlation, array( 'public_id' => $version->to_string() ) );
				return $version;
			}
		);
	}

	/**
	 * Deny all unknown actions and cross-organization objects.
	 *
	 * @param Actor        $actor Trusted WordPress actor.
	 * @param PolicyObject $domain_object Trusted policy resource.
	 * @throws RuntimeException When scope or capability is missing.
	 */
	private function authorize( Actor $actor, PolicyObject $domain_object ): void {
		if ( ! $this->policy->can( $actor, 'form.manage', $domain_object )->allowed ) {
			throw new RuntimeException( 'Form access denied.' );
		}
	}
}
