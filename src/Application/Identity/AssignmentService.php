<?php
/**
 * Audited manager-authorized actor assignments.
 *
 * @package UOP
 */

namespace UOP\Application\Identity;

use RuntimeException;
use UOP\Application\Event\PostCommitPublisher;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\FieldDefinition;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AssignmentRepository;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\Connection;
use UOP\Infrastructure\Database\OutboxRepository;

/** A WordPress capability is necessary but never an assignment by itself. */
final class AssignmentService {
	/**
	 * Bind scoped administration commands.
	 *
	 * @param AssignmentRepository $assignments Scoped role assignments.
	 * @param Connection           $db          Trusted database adapter.
	 * @param PolicyService        $policy      Authorization service.
	 * @param TransactionManager   $tx          Atomic transaction boundary.
	 * @param AuditWriter          $audit       Append-only audit writer.
	 * @param OutboxRepository     $outbox      Durable event records.
	 */
	public function __construct(
		private AssignmentRepository $assignments,
		private Connection $db,
		private PolicyService $policy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox
	) {}

	/**
	 * Grant an explicitly scoped assignment after a current manager decision.
	 *
	 * @param Actor         $actor       Manager performing the change.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param int           $user_id     Existing WordPress user.
	 * @param string        $role_key    Whitelisted role bundle.
	 * @param string        $scope_type  Organization or event.
	 * @param int           $scope_id    Event ID or zero.
	 * @param string        $ceiling     Field sensitivity ceiling.
	 * @param string        $utc_now     UTC timestamp.
	 * @param CorrelationId $correlation Request correlation.
	 * @throws RuntimeException For denied or invalid assignments.
	 */
	public function grant( Actor $actor, OrgScope $scope, int $user_id, string $role_key, string $scope_type, int $scope_id, string $ceiling, string $utc_now, CorrelationId $correlation ): void {
		$domain_object = new PolicyObject( $scope->id, 'organization', $scope->id );
		if ( ! $this->policy->can( $actor, 'organization.manage', $domain_object )->allowed || $user_id < 1 || ! get_user_by( 'id', $user_id ) ) {
			throw new RuntimeException( 'Assignment not permitted.' );
		}
		if ( 'event' === $scope_type ) {
			$rows = $this->db->rows(
				'SELECT event_post_id FROM %i WHERE organization_id = %d AND event_post_id = %d LIMIT 1',
				array( $GLOBALS['wpdb']->prefix . 'uop_event_settings', $scope->id, $scope_id )
			);
			if ( ! $rows ) {
				throw new RuntimeException( 'Assignment event does not belong to this organization.' );
			}
		}
		$this->tx->run(
			function () use ( $actor, $scope, $user_id, $role_key, $scope_type, $scope_id, $ceiling, $utc_now, $correlation, $domain_object ): void {
				if ( ! $this->policy->can( $actor, 'organization.manage', $domain_object )->allowed
					|| ! $this->policy->can( $actor, 'person.view', $domain_object, new FieldDefinition( 'assignment_ceiling', $ceiling, true, true, true, true ) )->allowed ) {
					throw new RuntimeException( 'Assignment no longer permitted.' );
				}
				$id   = $this->assignments->grant( $scope, $user_id, $role_key, $scope_type, $scope_id, $ceiling, $utc_now );
				$uuid = PublicId::generate();
				$this->audit->append( $scope, $actor, 'assignment.granted', $domain_object, 'success', $correlation, $uuid );
				$this->outbox->append( $scope, $uuid, 'assignment', $id, 'assignment.granted', $correlation, array( 'status' => 'active' ) );
			}
		);
	}
	/**
	 * Revoke one scoped assignment and emit matching audit/outbox evidence.
	 *
	 * @param Actor         $actor       Manager performing the revoke.
	 * @param OrgScope      $scope       Trusted organization.
	 * @param int           $id          Internal assignment key, never public REST input.
	 * @param string        $utc_now     UTC revocation timestamp.
	 * @param CorrelationId $correlation Request correlation.
	 * @throws RuntimeException When assignment is inaccessible or not active.
	 */
	public function revoke( Actor $actor, OrgScope $scope, int $id, string $utc_now, CorrelationId $correlation ): void {
		$this->tx->run(
			function () use ( $actor, $scope, $id, $utc_now, $correlation ): void {
				$object = new PolicyObject( $scope->id, 'organization', $scope->id );
				$row    = $this->assignments->find( $scope, $id );
				if ( ! $row || ! $this->policy->can( $actor, 'organization.manage', $object )->allowed
					|| ! $this->assignments->revoke( $scope, $id, $utc_now ) ) {
					throw new RuntimeException( 'Assignment revocation not permitted.' );
				}
				$event = PublicId::generate();
				$this->audit->append( $scope, $actor, 'assignment.revoked', $object, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'assignment', $id, 'assignment.revoked', $correlation, array( 'status' => 'revoked' ) );
			}
		);
	}
}
