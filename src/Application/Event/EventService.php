<?php
/**
 * Authorized operations on existing editorial Event posts.
 *
 * @package UOP
 */

namespace UOP\Application\Event;

use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Events\OccurrenceWindow;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\EventRepository;
use UOP\Infrastructure\Database\OccurrenceRepository;
use UOP\Infrastructure\Database\OutboxRepository;

/** No recurrence rules; no operational CPT post meta; no self-service registration. */
final class EventService {
	/**
	 * Compose trusted event command dependencies.
	 *
	 * @param EventRepository      $events Event settings.
	 * @param OccurrenceRepository $occurrences Manual occurrence persistence.
	 * @param PolicyService        $policy Central policy.
	 * @param TransactionManager   $tx Transaction manager.
	 * @param AuditWriter          $audit Audit evidence.
	 * @param OutboxRepository     $outbox Durable event outbox.
	 */
	public function __construct(
		private EventRepository $events,
		private OccurrenceRepository $occurrences,
		private PolicyService $policy,
		private TransactionManager $tx,
		private AuditWriter $audit,
		private OutboxRepository $outbox
	) {}

	/**
	 * Attach operational configuration to an existing editable Event CPT post.
	 *
	 * @param Actor         $actor Current manager.
	 * @param OrgScope      $scope Organization.
	 * @param int           $post_id Trusted WordPress event ID.
	 * @param string        $zone IANA timezone.
	 * @param string        $utc_now UTC timestamp.
	 * @param CorrelationId $correlation Command correlation.
	 * @return PublicId
	 * @throws RuntimeException For unauthorized or wrong post type.
	 */
	public function configure( Actor $actor, OrgScope $scope, int $post_id, string $zone, string $utc_now, CorrelationId $correlation ): PublicId {
		$post     = get_post( $post_id );
		$resource = new PolicyObject( $scope->id, 'event', $post_id, null, $post_id );
		if ( ! $post || 'uop_event' !== $post->post_type || ! user_can( $actor->user_id, 'edit_post', $post_id )
			|| ! $this->policy->can( $actor, 'event.manage', $resource )->allowed
			|| ! in_array( $zone, DateTimeZone::listIdentifiers(), true ) ) {
			throw new RuntimeException( 'Event configuration not permitted.' );
		}
		$uuid = PublicId::generate();
		$this->tx->run(
			function () use ( $actor, $scope, $post_id, $zone, $utc_now, $correlation, $resource, $uuid ): void {
				if ( ! $this->policy->can( $actor, 'event.manage', $resource )->allowed || $this->events->find( $scope, $post_id ) ) {
					throw new RuntimeException( 'Event already configured or denied.' );
				}
				$this->events->create( $scope, $uuid, $post_id, $zone, $utc_now );
				$event = PublicId::generate();
				$this->audit->append( $scope, $actor, 'event.configured', $resource, 'success', $correlation, $event );
				$this->outbox->append( $scope, $event, 'event', $post_id, 'event.configured', $correlation, array( 'public_id' => $uuid->to_string() ) );
			}
		);
		return $uuid;
	}

	/**
	 * Schedule one DST-explicit occurrence under existing event authorization.
	 *
	 * @param Actor            $actor Current manager.
	 * @param OrgScope         $scope Organization.
	 * @param int              $post_id Event post ID.
	 * @param OccurrenceWindow $window Verified offset/IANA interval.
	 * @param string           $utc_now UTC timestamp.
	 * @param CorrelationId    $correlation Command correlation.
	 * @return PublicId
	 * @throws RuntimeException For wrong timezone or forbidden event.
	 */
	public function add_occurrence( Actor $actor, OrgScope $scope, int $post_id, OccurrenceWindow $window, string $utc_now, CorrelationId $correlation ): PublicId {
		$resource = new PolicyObject( $scope->id, 'event', $post_id, null, $post_id );
		$uuid     = PublicId::generate();
		$this->tx->run(
			function () use ( $actor, $scope, $post_id, $window, $utc_now, $correlation, $resource, $uuid ): void {
				$event = $this->events->find( $scope, $post_id );
				if ( ! $event || 'active' !== $event['status'] || $event['timezone'] !== $window->zone
					|| ! user_can( $actor->user_id, 'edit_post', $post_id )
					|| ! $this->policy->can( $actor, 'event.manage', $resource )->allowed ) {
					throw new RuntimeException( 'Occurrence outside authorized event timezone or scope.' );
				}
				$this->occurrences->create( $scope, $uuid, $post_id, $window, $utc_now );
				$occurred = PublicId::generate();
				$this->audit->append( $scope, $actor, 'event.occurrence_created', $resource, 'success', $correlation, $occurred );
				$this->outbox->append( $scope, $occurred, 'event', $post_id, 'event.occurrence_created', $correlation, array( 'public_id' => $uuid->to_string() ) );
			}
		);
		return $uuid;
	}
}
