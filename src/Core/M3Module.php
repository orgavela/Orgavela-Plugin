<?php
/**
 * Explicit M3 profile, event and form composition.
 *
 * @package UOP
 */

namespace UOP\Core;

use UOP\Application\Event\EventService;
use UOP\Application\Form\FormService;
use UOP\Application\Profile\ProfileService;
use UOP\Application\Query\M3ReadService;
use UOP\Application\Policy\ProjectionService;
use UOP\Admin\M3FormBuilderScreen;
use UOP\REST\M3Controller;
use UOP\Application\Policy\PolicyService;
use UOP\Extension\ModuleInterface;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\Connection;
use UOP\Infrastructure\Database\EventRepository;
use UOP\Infrastructure\Database\FormRepository;
use UOP\Infrastructure\Database\OccurrenceRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\ProfileFieldRepository;
use UOP\Infrastructure\Database\ProfileValueRepository;

/** M3 commands reuse M2 policy, audit and transactions. */
final class M3Module implements ModuleInterface {
	/**
	 * Stable feature milestone key.
	 *
	 * @return string
	 */
	public function key(): string {
		return 'profiles_events_forms';
	}

	/**
	 * Lazily bind new application services after the M2 identity module.
	 *
	 * @param ServiceContainer $container Shared composition.
	 */
	public function register( ServiceContainer $container ): void {
		global $wpdb;
		$prefix = $wpdb->prefix . 'uop_';
		$container->set( ProfileFieldRepository::class, static fn ( ServiceContainer $c ) => new ProfileFieldRepository( $c->get( Connection::class ), $prefix ) );
		$container->set( ProfileValueRepository::class, static fn ( ServiceContainer $c ) => new ProfileValueRepository( $c->get( Connection::class ), $prefix ) );
		$container->set( EventRepository::class, static fn ( ServiceContainer $c ) => new EventRepository( $c->get( Connection::class ), $prefix ) );
		$container->set( OccurrenceRepository::class, static fn ( ServiceContainer $c ) => new OccurrenceRepository( $c->get( Connection::class ), $prefix ) );
		$container->set( FormRepository::class, static fn ( ServiceContainer $c ) => new FormRepository( $c->get( Connection::class ), $prefix ) );
		$container->set(
			ProfileService::class,
			static fn ( ServiceContainer $c ) => new ProfileService(
				$c->get( ProfileFieldRepository::class ),
				$c->get( ProfileValueRepository::class ),
				$c->get( PersonRepository::class ),
				$c->get( PolicyService::class ),
				$c->get( TransactionManager::class ),
				$c->get( AuditWriter::class ),
				$c->get( OutboxRepository::class )
			)
		);
		$container->set(
			EventService::class,
			static fn ( ServiceContainer $c ) => new EventService(
				$c->get( EventRepository::class ),
				$c->get( OccurrenceRepository::class ),
				$c->get( PolicyService::class ),
				$c->get( TransactionManager::class ),
				$c->get( AuditWriter::class ),
				$c->get( OutboxRepository::class )
			)
		);
		$container->set(
			FormService::class,
			static fn ( ServiceContainer $c ) => new FormService(
				$c->get( FormRepository::class ),
				$c->get( PolicyService::class ),
				$c->get( TransactionManager::class ),
				$c->get( AuditWriter::class ),
				$c->get( OutboxRepository::class )
			)
		);
		$container->set(
			M3ReadService::class,
			static fn ( ServiceContainer $c ) => new M3ReadService(
				$c->get( PersonRepository::class ),
				$c->get( ProfileFieldRepository::class ),
				$c->get( ProfileValueRepository::class ),
				$c->get( EventRepository::class ),
				$c->get( OccurrenceRepository::class ),
				$c->get( FormRepository::class ),
				$c->get( PolicyService::class ),
				$c->get( ProjectionService::class )
			)
		);
		$container->set(
			M3Controller::class,
			static fn ( ServiceContainer $c ) => new M3Controller(
				$c->get( M3ReadService::class ),
				$c->get( FormService::class ),
				$c->get( PolicyService::class )
			)
		);
		$container->set(
			M3FormBuilderScreen::class,
			static fn ( ServiceContainer $c ) => new M3FormBuilderScreen( $c->get( PolicyService::class ) )
		);
		add_action( 'rest_api_init', array( $container->get( M3Controller::class ), 'register' ) );
		$builder = $container->get( M3FormBuilderScreen::class );
		add_action( 'admin_menu', array( $builder, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $builder, 'assets' ) );
	}
}
