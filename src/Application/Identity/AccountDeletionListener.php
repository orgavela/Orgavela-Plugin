<?php
/**
 * WordPress user deletion leaves organization-owned people intact.
 *
 * @package UOP
 */

namespace UOP\Application\Identity;

use UOP\Application\Event\PostCommitPublisher;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AssignmentRepository;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\Connection;
use UOP\Infrastructure\Database\DelegationRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;

/** Invoked only by the trusted WordPress deleted_user lifecycle hook. */
final class AccountDeletionListener {
	/**
	 * Compose database-backed identity cleanup.
	 *
	 * @param Connection           $db          Database connection.
	 * @param string               $prefix      Site table prefix.
	 * @param PersonRepository     $people      People persistence.
	 * @param DelegationRepository $delegations Delegations to revoke.
	 * @param AssignmentRepository $assignments Assignments to revoke.
	 * @param TransactionManager   $tx          Atomic transaction manager.
	 * @param AuditWriter          $audit       Audit persistence.
	 * @param OutboxRepository     $outbox      Transactional event storage.
	 * @param PostCommitPublisher  $publisher   Post-commit hooks.
	 */
	public function __construct(
		private Connection $db,
		private string $prefix,
		private PersonRepository $people,
		private DelegationRepository $delegations,
		private AssignmentRepository $assignments,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox,
		private PostCommitPublisher $publisher
	) {}

	/**
	 * Detach accounts and revoke their grants, retaining historical person records.
	 *
	 * @param int $user_id WordPress-deleted user ID.
	 */
	public function handle( int $user_id ): void {
		if ( $user_id < 1 ) {
			return;
		}
		$organizations = $this->db->rows(
			"SELECT id FROM %i WHERE status = 'active' ORDER BY id ASC LIMIT 100",
			array( $this->prefix . 'organizations' )
		);
		foreach ( $organizations as $organization ) {
			$scope = new OrgScope( (int) $organization['id'] );
			$this->tx->run(
				function () use ( $scope, $user_id ): void {
					$person = $this->people->by_user( $scope, $user_id );
					$time   = gmdate( 'Y-m-d H:i:s' );
					$this->people->unlink_user( $scope, $user_id, $time );
					$this->delegations->revoke_for_actor( $scope, $user_id, $time );
					$this->assignments->revoke_for_actor( $scope, $user_id, $time );
					if ( ! $person ) {
						return;
					}
					$correlation = CorrelationId::generate();
					$event       = PublicId::generate();
					$person_id   = PublicId::from_binary( $person['public_id'] );
					$object      = new PolicyObject( $scope->id, 'person', (int) $person['id'] );
					$this->audit->append( $scope, new Actor( 0 ), 'person.account_unlinked', $object, 'success', $correlation, $event );
					$this->outbox->append( $scope, $event, 'person', (int) $person['id'], 'person.account_unlinked', $correlation, array( 'public_id' => $person_id->to_string() ) );
					$this->publisher->after_commit( 'person.account_unlinked', $event, $person_id );
				}
			);
		}
	}
}
