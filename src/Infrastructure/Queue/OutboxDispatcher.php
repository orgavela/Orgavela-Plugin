<?php
/**
 * Action Scheduler delivery of transactional outbox events.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Queue;

use Closure;
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
	 * @param Closure|null     $enqueue Optional isolated queue seam for synthetic tests.
	 * @phpstan-param Closure(int,string):void|null $enqueue
	 */
	public function __construct(
		private Connection $db,
		private OutboxRepository $outbox,
		private string $prefix,
		private ?Closure $enqueue = null
	) {}

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

	/**
	 * Requeue undelivered events after a crash between commit and enqueue.
	 *
	 * @throws \RuntimeException If the organization cursor fails to advance.
	 */
	public function sweep(): void {
		if ( null === $this->enqueue && ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}
		$last_id = 0;
		do {
			// Avoid silently starving every organization after the first 100.
			// Closed organizations remain excluded from new deliveries.
			$organizations = $this->db->rows(
				'SELECT id FROM %i WHERE status = %s AND id > %d ORDER BY id ASC LIMIT %d',
				array( $this->prefix . 'organizations', 'active', $last_id, 100 )
			);
			$batch_size = count( $organizations );
			foreach ( $organizations as $organization ) {
				$organization_id = (int) $organization['id'];
				if ( $organization_id <= $last_id ) {
					throw new \RuntimeException( 'Outbox organization pagination cursor did not advance.' );
				}
				$last_id = $organization_id;
				$scope   = new OrgScope( $organization_id );
				foreach ( $this->outbox->pending( $scope ) as $event ) {
					$uuid = PublicId::from_binary( $event['event_uuid'] )->to_string();
					if ( null !== $this->enqueue ) {
						( $this->enqueue )( $scope->id, $uuid );
					} else {
						as_enqueue_async_action( 'uop_outbox_consume', array( $scope->id, $uuid ), 'uop', true );
					}
				}
			}
		} while ( 100 === $batch_size );
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
