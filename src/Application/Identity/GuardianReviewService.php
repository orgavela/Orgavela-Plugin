<?php
/**
 * Two-party guardian claim and bounded, reviewer-controlled delegation.
 *
 * @package UOP
 */

namespace UOP\Application\Identity;

use InvalidArgumentException;
use RuntimeException;
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
use UOP\Infrastructure\Database\GuardianClaimQuotaRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RelationshipRepository;

/**
 * A claim never proves legal parenthood, grants portal access or links accounts.
 * A different, authorized reviewer must record an affirmative evidence check.
 */
final class GuardianReviewService {
	/**
	 * Bind scoped, transactional collaborators.
	 *
	 * @param PersonRepository       $people        Scoped person storage.
	 * @param RelationshipRepository $relationships Guardian claims.
	 * @param DelegationRepository   $delegations   Time-limited grants.
	 * @param Connection             $db            Prepared SQL adapter.
	 * @param PolicyService          $policy        Live scope checks.
	 * @param TransactionManager     $tx            Atomic transaction.
	 * @param AuditWriter            $audit         Append-only evidence.
	 * @param OutboxRepository       $outbox        Domain events.
	 * @param string                 $prefix        Trusted site table prefix.
	 * @param GuardianClaimQuotaRepository $quota    Transactional claim attempt quota.
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
		private string $prefix,
		private GuardianClaimQuotaRepository $quota
	) {}

	/**
	 * File a non-authorizing claim. Unrecognized subjects remain undisclosed.
	 *
	 * @param Actor         $claimant    Logged-in requesting account.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param PublicId      $subject     Opaque subject identity.
	 * @param string        $now         UTC request time.
	 * @param CorrelationId $correlation Trace reference.
	 * @return bool Whether a new pending claim was recorded internally.
	 * @throws \UOP\Infrastructure\Database\DatabaseException On a failed database operation, not a duplicate.
	 */
	public function request( Actor $claimant, OrgScope $scope, PublicId $subject, string $now, CorrelationId $correlation ): bool {
		if ( $claimant->user_id < 1 ) {
			return false;
		}
		$linked = $this->people->by_user( $scope, $claimant->user_id );
		if ( ! $linked || 'active' !== $linked['status'] ) {
			return false;
		}
		return $this->tx->run(
			function () use ( $claimant, $scope, $subject, $now, $correlation ): bool {
				// Consume before resolving any subject UUID, even unknown subjects.
				// The quota row remains locked through the claim transaction; no
				// WordPress transient or PHP-worker cache controls authorization.
				if ( ! $this->quota->consume( $scope, $claimant->user_id, time() ) ) {
					return false;
				}
				$linked = $this->people->by_user( $scope, $claimant->user_id );
				$target = $this->people->find( $scope, $subject );
				if ( ! $linked || 'active' !== $linked['status'] || ! $target || 'active' !== $target['status']
					|| (int) $target['id'] === (int) $linked['id'] ) {
					return false;
				}
				try {
					$uuid = PublicId::generate();
					$this->relationships->request_guardian( $scope, $uuid, (int) $linked['id'], (int) $target['id'], $now );
				} catch ( \\UOP\\Infrastructure\\Database\\DatabaseException $error ) {
					// Duplicate claims consume a slot, but create no additional
					// relationship, audit entry, event or permission.
					if ( 1062 === $error->getCode() ) {
						return false;
					}
					throw $error;
				}
				$event  = PublicId::generate();
				$object = new PolicyObject( $scope->id, 'relationship', (int) $target['id'], (int) $target['id'] );
				$this->audit->append( $scope, $claimant, 'guardian.requested', $object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'person', (int) $target['id'], 'guardian.requested', $correlation, array( 'public_id' => $uuid->to_string() ) );
				return true;
			}
		);
	}

	/**
	 * A bounded private queue; guarded by an object-level reviewer capability.
	 *
	 * @param Actor    $reviewer Current reviewer.
	 * @param OrgScope $scope    Owning organization.
	 * @return list<array<string,mixed>>
	 */
	public function pending( Actor $reviewer, OrgScope $scope ): array {
		$this->require_manager( $reviewer, $scope );
		$items = array();
		foreach ( $this->relationships->pending_guardians( $scope ) as $record ) {
			$items[] = array(
				'public_id'  => PublicId::from_binary( (string) $record['public_id'] )->to_string(),
				'claimant'   => (string) $record['claimant'],
				'subject'    => (string) $record['subject'],
				'created_at' => (string) $record['created_at'],
			);
		}
		return $items;
	}

	/**
	 * Approve only after verification, granting event-only 90-day authority.
	 * Rejection makes no relationship active and creates no delegation.
	 *
	 * @param Actor         $reviewer         Reviewer with organization authority.
	 * @param OrgScope      $scope            Owning organization.
	 * @param PublicId      $claim            Pending claim.
	 * @param string        $decision         Approve or reject.
	 * @param bool          $evidence_checked Explicit external evidence attestation.
	 * @param int           $event_post_id    Scoped WordPress event.
	 * @param string        $now              UTC decision time.
	 * @param CorrelationId $correlation      Trace reference.
	 * @return PublicId|null Granted delegation ID on verified approval.
	 * @throws InvalidArgumentException For insufficient approval metadata.
	 */
	public function review(
		Actor $reviewer,
		OrgScope $scope,
		PublicId $claim,
		string $decision,
		bool $evidence_checked,
		int $event_post_id,
		string $now,
		CorrelationId $correlation
	): ?PublicId {
		$this->require_manager( $reviewer, $scope );
		if ( ! in_array( $decision, array( 'approve', 'reject' ), true ) || ( 'approve' === $decision && ( ! $evidence_checked || $event_post_id < 1 ) ) ) {
			throw new InvalidArgumentException( 'Review requires an explicit evidence check and valid event scope.' );
		}
		return $this->tx->run(
			function () use ( $reviewer, $scope, $claim, $decision, $event_post_id, $now, $correlation ): ?PublicId {
				$row = $this->relationships->lock_pending_guardian( $scope, $claim );
				if ( ! $row ) {
					throw new RuntimeException( 'Guardian claim already processed or unavailable.' );
				}
				$from    = $this->people->by_internal_id( $scope, (int) $row['from_person_id'] );
				$to      = $this->people->by_internal_id( $scope, (int) $row['to_person_id'] );
				$grantee = (int) ( $from['wp_user_id'] ?? 0 );
				// An orphaned claim must remain rejectable, but never approvable.
				// Unlinking or archiving a person cannot become a new permission.
				if ( ! $to ) {
					throw new RuntimeException( 'Guardian request subject no longer exists.' );
				}
				if ( 'approve' === $decision && ( ! $from || 'active' !== $from['status'] || 'active' !== $to['status']
				|| $grantee < 1 || $grantee === $reviewer->user_id
				|| (int) ( $to['wp_user_id'] ?? 0 ) === $reviewer->user_id
				|| ! get_user_by( 'id', $grantee ) ) ) {
					throw new RuntimeException( 'Guardian claim must be reviewed for two distinct active identities.' );
				}
				if ( 'approve' === $decision ) {
					$event = $this->db->rows(
						'SELECT event_post_id FROM %i WHERE organization_id = %d AND event_post_id = %d LIMIT 1',
						array( $this->prefix . 'event_settings', $scope->id, $event_post_id )
					);
					$post  = get_post( $event_post_id );
					if ( ! $event || ! $post || 'uop_event' !== $post->post_type || 'publish' !== $post->post_status ) {
						throw new RuntimeException( 'Event is outside review scope or not published.' );
					}
					$existing = $this->delegations->by_grant_key( $scope, $grantee, (int) $to['id'], 'registration_manage', 'event', $event_post_id );
					if ( $existing && 'active' === $existing['status'] ) {
						throw new RuntimeException( 'An active event delegation already exists.' );
					}
				}
				$status = 'approve' === $decision ? 'active' : 'rejected';
				if ( ! $this->relationships->decide_guardian( $scope, (int) $row['id'], $status, $now ) ) {
					throw new RuntimeException( 'Concurrent guardian review conflict.' );
				}
				$grant = null;
				if ( 'approve' === $decision ) {
					$uuid = PublicId::generate();
					$this->delegations->grant( $scope, $uuid, $grantee, (int) $to['id'], 'registration_manage', 'event', $event_post_id, (int) $row['id'], $now );
					$grant_row = $this->delegations->by_grant_key( $scope, $grantee, (int) $to['id'], 'registration_manage', 'event', $event_post_id );
					if ( ! $grant_row ) {
						throw new RuntimeException( 'Review grant was not persisted.' );
					}
					$grant   = PublicId::from_binary( (string) $grant_row['public_id'] );
					$expires = gmdate( 'Y-m-d H:i:s', strtotime( $now . ' UTC +90 days' ) );
					if ( 1 !== $this->db->execute(
						'UPDATE %i SET valid_to = %s WHERE organization_id = %d AND id = %d AND relationship_id = %d',
						array( $this->prefix . 'delegations', $expires, $scope->id, (int) $grant_row['id'], (int) $row['id'] )
					) ) {
						throw new RuntimeException( 'Guardian grant expiry was not persisted.' );
					}
				}
				$event_id = PublicId::generate();
				$object   = new PolicyObject( $scope->id, 'relationship', (int) $row['id'], (int) $to['id'] );
				$action   = 'approve' === $decision ? 'guardian.approved' : 'guardian.rejected';
				$this->audit->append( $scope, $reviewer, $action, $object, 'success', $correlation, $event_id, array( 'reason_code' => 'approve' === $decision ? 'offline_evidence_checked' : 'not_approved' ) );
				$this->outbox->append( $scope, $event_id, 'person', (int) $to['id'], $action, $correlation, array( 'public_id' => $claim->to_string() ) );
				return $grant;
			}
		);
	}

	/**
	 * Enforce the same live object-scoped policy for every reviewer action.
	 *
	 * @param Actor    $actor Reviewer identity.
	 * @param OrgScope $scope Owning organization.
	 * @throws RuntimeException For insufficient reviewer permissions.
	 */
	private function require_manager( Actor $actor, OrgScope $scope ): void {
		if ( ! $this->policy->can( $actor, 'delegation.manage', new PolicyObject( $scope->id, 'organization', $scope->id ) )->allowed ) {
			throw new RuntimeException( 'Guardian review requires organization manager permission.' );
		}
	}
}
