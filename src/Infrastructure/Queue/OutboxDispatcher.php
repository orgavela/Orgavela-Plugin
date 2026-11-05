<?php
/**
 * Action Scheduler delivery of transactional outbox events.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Queue;

use Throwable;
use UOP\Application\Event\DomainEventDto;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\Connection;
use UOP\Infrastructure\Database\OutboxRepository;

/** Sweeps committed events; consumers are explicitly at-least-once. */
final class OutboxDispatcher {
	/**
	 * Bind queue storage and the site's database.
	 *
	 * @param Connection       $db     Site database adapter.
	 * @param OutboxRepository $outbox Persistent domain events.
	 * @param string           $prefix Trusted WordPress table prefix.
	 */
	public function __construct( private Connection $db, private OutboxRepository $outbox, private string $prefix ) {}

	/** Register the private Action Scheduler jobs without running them in HTTP requests. */
	public function register_hooks(): void {
		add_action( 'uop_outbox_sweep', array( $this, 'sweep' ) );
		add_action( 'uop_outbox_consume', array( $this, 'consume' ), 10, 2 );
		add_action(
			'init',
			static function (): void {
				if ( function_exists( 'as_schedule_recurring_action' ) && function_exists( 'as_next_scheduled_action' ) && false === as_next_scheduled_action( 'uop_outbox_sweep', array(), 'uop' ) ) {
					as_schedule_recurring_action( time() + 60, 300, 'uop_outbox_sweep', array(), 'uop', true );
				}
			},
			25
		);
	}

	/** Requeue undelivered events after a crash between commit and enqueue. */
	public function sweep(): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}
		$organizations = $this->db->rows(
			"SELECT id FROM %i WHERE status = 'active' ORDER BY id ASC LIMIT 100",
			array( $this->prefix . 'organizations' )
		);
		foreach ( $organizations as $organization ) {
			$scope = new OrgScope( (int) $organization['id'] );
			foreach ( $this->outbox->pending( $scope ) as $event ) {
				$uuid = PublicId::from_binary( $event['event_uuid'] )->to_string();
				as_enqueue_async_action( 'uop_outbox_consume', array( $scope->id, $uuid ), 'uop', true );
			}
		}
	}

	/**
	 * Publish a committed event and acknowledge only after dispatch completes.
	 *
	 * @param int    $organization_id Organization trusted from the queue.
	 * @param string $event_uuid      Canonical event UUID.
	 */
	public function consume( int $organization_id, string $event_uuid ): void {
		if ( $organization_id < 1 ) {
			return;
		}
		$scope = new OrgScope( $organization_id );
		$uuid  = PublicId::from_string( $event_uuid );
		$event = $this->outbox->find_pending( $scope, $uuid );
		if ( ! $event ) {
			return;
		}
		try {
			$payload = json_decode( (string) $event['payload_json'], true, 64, JSON_THROW_ON_ERROR );
			$dto     = new DomainEventDto( $event_uuid, (string) $event['event_name'], (string) ( $payload['public_id'] ?? '' ) );
			do_action( 'uop_domain_event', $dto );
			$this->outbox->mark_published( $scope, $uuid );
		} catch ( Throwable $error ) {
			$this->outbox->failed_attempt( $scope, $uuid, get_class( $error ) );
		}
	}
}
