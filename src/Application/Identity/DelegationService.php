<?php
/**
 * Audited and explicit authority for managing another person.
 *
 * @package UOP
 */

namespace UOP\Application\Identity;

use RuntimeException;
use UOP\Application\Event\PostCommitPublisher;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\Connection;
use UOP\Infrastructure\Database\DelegationRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RelationshipRepository;

/** A guardian_of relationship alone never grants authorization. */
final class DelegationService {
	/**
	 * Bind the security-sensitive delegation command boundary.
	 *
	 * @param PersonRepository       $people        Scoped people.
	 * @param RelationshipRepository $relationships Scoped relationships.
	 * @param DelegationRepository   $delegations   Scoped grants.
	 * @param Connection             $db            Read-only event scope checks.
	 * @param PolicyService          $policy        Authoritative policy.
	 * @param TransactionManager     $tx            Transaction service.
	 * @param AuditWriter            $audit         Audit writer.
	 * @param OutboxRepository       $outbox        Durable domain events.
	 * @param PostCommitPublisher    $publisher     Public post-commit hooks.
	 */
	public function __construct(
		private PersonRepository $people,
		private RelationshipRepository $relationships,
		private DelegationRepository $delegations,
		private Connection $db,
		private PolicyService $policy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox,
		private PostCommitPublisher $publisher
	) {}

	/**
	 * Grant a verified, explicit delegation to an existing WordPress account.
	 *
	 * @param Actor         $manager          Authorized administrative actor.
	 * @param OrgScope      $scope            Trusted organization.
	 * @param int           $grantee_user_id  WordPress actor receiving the grant.
	 * @param PublicId      $subject_id       Target person public ID.
	 * @param string        $permission       Whitelisted permission set.
	 * @param string        $scope_type       Organization or event.
	 * @param int           $scope_id         Event ID or organization zero.
	 * @param PublicId|null $relationship_id  Optional evidence of relationship.
	 * @param string        $utc_now          Current UTC timestamp.
	 * @param CorrelationId $correlation      Command trace.
	 * @return PublicId Canonical delegation public ID.
	 * @throws RuntimeException If identity, scope or policy checks fail.
	 */
	public function grant( Actor $manager, OrgScope $scope, int $grantee_user_id, PublicId $subject_id, string $permission, string $scope_type, int $scope_id, ?PublicId $relationship_id, string $utc_now, CorrelationId $correlation ): PublicId {
		if ( $grantee_user_id < 1 || ! get_user_by( 'id', $grantee_user_id ) ) {
			throw new RuntimeException( 'Unknown delegate account.' );
		}
		$subject = $this->people->find( $scope, $subject_id );
		if ( ! $subject || 'active' !== $subject['status'] || ! $this->policy->can( $manager, 'delegation.manage', new PolicyObject( $scope->id, 'person', (int) $subject['id'] ) )->allowed ) {
			throw new RuntimeException( 'Delegation not permitted.' );
		}
		if ( 'event' === $scope_type && ! $this->db->rows( 'SELECT event_post_id FROM %i WHERE organization_id = %d AND event_post_id = %d LIMIT 1', array( $this->table( 'event_settings' ), $scope->id, $scope_id ) ) ) {
			throw new RuntimeException( 'Invalid delegation event scope.' );
		}
		$relationship = null;
		if ( null !== $relationship_id ) {
			$relationship   = $this->relationships->find( $scope, $relationship_id );
			$grantee_person = $this->people->by_user( $scope, $grantee_user_id );
			if ( ! $relationship || ! $grantee_person || 'active' !== $relationship['status'] || (int) $relationship['from_person_id'] !== (int) $grantee_person['id'] || (int) $relationship['to_person_id'] !== (int) $subject['id'] ) {
				throw new RuntimeException( 'Delegation relationship evidence mismatch.' );
			}
		}
		return $this->tx->run(
			function () use ( $manager, $scope, $grantee_user_id, $subject, $permission, $scope_type, $scope_id, $relationship, $utc_now, $correlation ): PublicId {
				if ( ! $this->policy->can( $manager, 'delegation.manage', new PolicyObject( $scope->id, 'person', (int) $subject['id'] ) )->allowed ) {
					throw new RuntimeException( 'Delegation no longer permitted.' );
				}
				$new_id = PublicId::generate();
				$this->delegations->grant( $scope, $new_id, $grantee_user_id, (int) $subject['id'], $permission, $scope_type, $scope_id, $relationship ? (int) $relationship['id'] : null, $utc_now );
				$grant = $this->delegations->by_grant_key( $scope, $grantee_user_id, (int) $subject['id'], $permission, $scope_type, $scope_id );
				if ( ! $grant ) {
					throw new RuntimeException( 'Delegation could not be verified.' );
				}
				$public_id     = PublicId::from_binary( $grant['public_id'] );
				$event         = PublicId::generate();
				$domain_object = new PolicyObject( $scope->id, 'delegation', (int) $grant['id'], (int) $subject['id'] );
				$this->audit->append( $scope, $manager, 'delegation.granted', $domain_object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'delegation', (int) $grant['id'], 'delegation.granted', $correlation, array( 'public_id' => $public_id->to_string() ) );
				$this->publisher->after_commit( 'delegation.granted', $event, $public_id );
				return $public_id;
			}
		);
	}

	/**
	 * Revoke a delegation without deleting the underlying relationship.
	 *
	 * @param Actor         $manager     Administrative actor.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param PublicId      $public_id   Delegation public UUID.
	 * @param string        $utc_now     Current UTC timestamp.
	 * @param CorrelationId $correlation Request trace.
	 * @throws RuntimeException If the grant cannot be revoked.
	 */
	public function revoke( Actor $manager, OrgScope $scope, PublicId $public_id, string $utc_now, CorrelationId $correlation ): void {
		$this->tx->run(
			function () use ( $manager, $scope, $public_id, $utc_now, $correlation ): void {
				$grant = $this->delegations->find( $scope, $public_id );
				if ( ! $grant || ! $this->policy->can( $manager, 'delegation.manage', new PolicyObject( $scope->id, 'delegation', (int) $grant['id'], (int) $grant['subject_person_id'] ) )->allowed || ! $this->delegations->revoke( $scope, $public_id, $utc_now ) ) {
					throw new RuntimeException( 'Delegation revocation denied or already processed.' );
				}
				$event         = PublicId::generate();
				$domain_object = new PolicyObject( $scope->id, 'delegation', (int) $grant['id'], (int) $grant['subject_person_id'] );
				$this->audit->append( $scope, $manager, 'delegation.revoked', $domain_object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'delegation', (int) $grant['id'], 'delegation.revoked', $correlation, array( 'public_id' => $public_id->to_string() ) );
				$this->publisher->after_commit( 'delegation.revoked', $event, $public_id );
			}
		);
	}

	/**
	 * Resolve a frozen table name from the current WordPress site only.
	 *
	 * @param string $name Internal known table suffix.
	 * @return string
	 */
	private function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'uop_' . $name;
	}
}
