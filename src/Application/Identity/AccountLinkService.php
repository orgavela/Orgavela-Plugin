<?php
/**
 * Explicit, audited WordPress-account linking.
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
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;

/** Matching email addresses never grant ownership or access. */
final class AccountLinkService {
	/**
	 * Bind policy-guarded identity commands.
	 *
	 * @param PersonRepository    $persons      Scoped people.
	 * @param PolicyService       $policy       Authoritative policy.
	 * @param TransactionManager  $transactions Atomic transaction.
	 * @param AuditWriter         $audit        Durable audit trail.
	 * @param OutboxRepository    $outbox       Durable events.
	 * @param PostCommitPublisher $publisher    Post-commit hooks.
	 */
	public function __construct(
		private PersonRepository $persons,
		private PolicyService $policy,
		private TransactionManager $transactions,
		private AuditWriter $audit,
		private OutboxRepository $outbox,
		private PostCommitPublisher $publisher
	) {}

	/**
	 * Link one unlinked person to an existing WP account with audited provenance.
	 *
	 * @param Actor         $actor       Authorized manager.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param PublicId      $person_id   Target person UUID.
	 * @param int           $user_id     Existing WordPress user.
	 * @param string        $utc_now     Timestamp in UTC.
	 * @param CorrelationId $correlation Trace identifier.
	 * @throws RuntimeException For forbidden or conflicting links.
	 */
	public function link( Actor $actor, OrgScope $scope, PublicId $person_id, int $user_id, string $utc_now, CorrelationId $correlation ): void {
		if ( $user_id < 1 || ! get_user_by( 'id', $user_id ) ) {
			throw new RuntimeException( 'Unknown WordPress account.' );
		}
		$event_uuid = PublicId::generate();
		$this->transactions->run(
			function () use ( $actor, $scope, $person_id, $user_id, $utc_now, $correlation, $event_uuid ): void {
				$row = $this->persons->find( $scope, $person_id );
				if ( ! $row || ! $this->policy->can( $actor, 'person.link', new PolicyObject( $scope->id, 'person', (int) $row['id'] ) )->allowed ) {
					throw new RuntimeException( 'Account link not permitted.' );
				}
				if ( ! $this->persons->link( $scope, $person_id, $user_id, $utc_now ) ) {
					throw new RuntimeException( 'Person is already linked or inactive.' );
				}
				$object = new PolicyObject( $scope->id, 'person', (int) $row['id'] );
				$this->audit->append( $scope, $actor, 'person.account_linked', $object, 'success', $correlation, $event_uuid );
				$this->outbox->append( $scope, $event_uuid, 'person', (int) $row['id'], 'person.account_linked', $correlation, array( 'public_id' => $person_id->to_string() ) );
				$this->publisher->after_commit( 'person.account_linked', $event_uuid, $person_id );
			}
		);
	}
}
