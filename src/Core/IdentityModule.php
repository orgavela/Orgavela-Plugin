<?php
/**
 * M2 composition of identity, policy, audit and outbox services.
 *
 * @package UOP
 */

namespace UOP\Core;

use UOP\Application\Event\PostCommitPublisher;
use UOP\Application\Identity\AccountDeletionListener;
use UOP\Application\Identity\AccountLinkService;
use UOP\Application\Identity\AssignmentService;
use UOP\Application\Identity\DelegationService;
use UOP\Application\Identity\PersonService;
use UOP\Application\Policy\CapabilityRegistry;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Policy\ProjectionService;
use UOP\Extension\ModuleInterface;
use UOP\Infrastructure\Database\AssignmentRepository;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\Connection;
use UOP\Infrastructure\Database\DelegationRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RelationshipRepository;
use UOP\Infrastructure\Database\WpdbConnection;
use UOP\Infrastructure\Queue\OutboxDispatcher;

/** A narrow composition root, not a public CRUD facade. */
final class IdentityModule implements ModuleInterface {
	/**
	 * Return the registered module key.
	 *
	 * @return string
	 */
	public function key(): string {
		return 'identity';
	}

	/**
	 * Register services once after the environment and schema gates succeed.
	 *
	 * @param ServiceContainer $container Explicit service dependencies.
	 */
	public function register( ServiceContainer $container ): void {
		global $wpdb;
		$prefix = $wpdb->prefix . 'uop_';
		$container->set( Connection::class, static fn () => new WpdbConnection( $wpdb ) );
		$container->set( PersonRepository::class, static fn ( ServiceContainer $c ) => new PersonRepository( $c->get( Connection::class ), $prefix ) );
		$container->set( RelationshipRepository::class, static fn ( ServiceContainer $c ) => new RelationshipRepository( $c->get( Connection::class ), $prefix ) );
		$container->set( DelegationRepository::class, static fn ( ServiceContainer $c ) => new DelegationRepository( $c->get( Connection::class ), $prefix ) );
		$container->set( AssignmentRepository::class, static fn ( ServiceContainer $c ) => new AssignmentRepository( $c->get( Connection::class ), $prefix ) );
		$container->set( AuditWriter::class, static fn ( ServiceContainer $c ) => new AuditWriter( $c->get( Connection::class ), $prefix ) );
		$container->set( OutboxRepository::class, static fn ( ServiceContainer $c ) => new OutboxRepository( $c->get( Connection::class ), $prefix ) );
		$container->set(
			TransactionManager::class,
			static fn ( ServiceContainer $c ) => new TransactionManager(
				$c->get( Connection::class ),
				static fn ( int $microseconds ) => usleep( $microseconds ),
				static function ( \Throwable $error ): void {
					update_option(
						'uop_post_commit_error',
						array(
							'type' => get_class( $error ),
							'code' => $error->getCode(),
						),
						false
					);
				}
			)
		);
		$container->set(
			PolicyService::class,
			static fn ( ServiceContainer $c ) => new PolicyService(
				$c->get( PersonRepository::class ),
				$c->get( DelegationRepository::class ),
				$c->get( AssignmentRepository::class ),
				static fn ( int $user_id, string $capability ): bool => user_can( $user_id, $capability )
			)
		);
		$container->set( ProjectionService::class, static fn ( ServiceContainer $c ) => new ProjectionService( $c->get( PolicyService::class ) ) );
		$container->set( PostCommitPublisher::class, static fn ( ServiceContainer $c ) => new PostCommitPublisher( $c->get( TransactionManager::class ) ) );
		$container->set(
			PersonService::class,
			static fn ( ServiceContainer $c ) => new PersonService(
				$c->get( PersonRepository::class ),
				$c->get( PolicyService::class ),
				$c->get( TransactionManager::class ),
				$c->get( AuditWriter::class ),
				$c->get( OutboxRepository::class ),
				$c->get( PostCommitPublisher::class )
			)
		);
		$container->set(
			AccountLinkService::class,
			static fn ( ServiceContainer $c ) => new AccountLinkService(
				$c->get( PersonRepository::class ),
				$c->get( PolicyService::class ),
				$c->get( TransactionManager::class ),
				$c->get( AuditWriter::class ),
				$c->get( OutboxRepository::class ),
				$c->get( PostCommitPublisher::class )
			)
		);
		$container->set(
			AccountDeletionListener::class,
			static fn ( ServiceContainer $c ) => new AccountDeletionListener(
				$c->get( Connection::class ),
				$prefix,
				$c->get( PersonRepository::class ),
				$c->get( DelegationRepository::class ),
				$c->get( AssignmentRepository::class ),
				$c->get( TransactionManager::class ),
				$c->get( AuditWriter::class ),
				$c->get( OutboxRepository::class ),
				$c->get( PostCommitPublisher::class )
			)
		);
		$container->set(
			OutboxDispatcher::class,
			static fn ( ServiceContainer $c ) => new OutboxDispatcher(
				$c->get( Connection::class ),
				$c->get( OutboxRepository::class ),
				$prefix
			)
		);

		$container->set(
			AssignmentService::class,
			static fn ( ServiceContainer $c ) => new AssignmentService(
				$c->get( AssignmentRepository::class ),
				$c->get( Connection::class ),
				$c->get( PolicyService::class ),
				$c->get( TransactionManager::class ),
				$c->get( AuditWriter::class ),
				$c->get( OutboxRepository::class )
			)
		);
		$container->set(
			DelegationService::class,
			static fn ( ServiceContainer $c ) => new DelegationService(
				$c->get( PersonRepository::class ),
				$c->get( RelationshipRepository::class ),
				$c->get( DelegationRepository::class ),
				$c->get( Connection::class ),
				$c->get( PolicyService::class ),
				$c->get( TransactionManager::class ),
				$c->get( AuditWriter::class ),
				$c->get( OutboxRepository::class ),
				$c->get( PostCommitPublisher::class )
			)
		);
		CapabilityRegistry::install();
		$dispatcher = $container->get( OutboxDispatcher::class );
		$dispatcher->register_hooks();
		add_action( 'deleted_user', array( $container->get( AccountDeletionListener::class ), 'handle' ), 10, 1 );
	}
}
