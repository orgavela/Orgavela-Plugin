<?php
/**
 * Durable-event and synchronous post-commit hooks.
 *
 * @package UOP
 */

namespace UOP\Application\Event;

use InvalidArgumentException;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;

/** Exceptions in extension hooks are isolated by TransactionManager after commit. */
final class PostCommitPublisher {
	/**
	 * Bind the transaction that owns the corresponding outbox write.
	 *
	 * @param TransactionManager $transactions Active command transaction manager.
	 */
	public function __construct( private TransactionManager $transactions ) {}

	/**
	 * Register an immutable DTO for best-effort publication after commit.
	 *
	 * @param string   $event_name Stable domain event identifier.
	 * @param PublicId $event_uuid  Committed event identity.
	 * @param PublicId $object_id   Public identifier of the changed object.
	 * @throws InvalidArgumentException For unsupported event identifiers.
	 */
	public function after_commit( string $event_name, PublicId $event_uuid, PublicId $object_id ): void {
		if ( ! preg_match( '/^[a-z]+(?:\.[a-z_]+)+$/D', $event_name ) ) {
			throw new InvalidArgumentException( 'Invalid public hook name.' );
		}
		$dto = new DomainEventDto( $event_uuid->to_string(), $event_name, $object_id->to_string() );
		$this->transactions->after_commit(
			static function () use ( $event_name, $dto ): void {
				do_action( 'uop_' . str_replace( '.', '_', $event_name ), $dto );
			}
		);
	}
}
