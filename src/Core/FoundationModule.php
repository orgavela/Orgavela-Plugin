<?php
/**
 * Foundation composition.
 *
 * @package UOP
 */

namespace UOP\Core;

use UOP\Extension\ModuleInterface;

/** Registers the core foundation without product features. */
final class FoundationModule implements ModuleInterface {
	/**
	 * Get the module identifier.
	 *
	 * @return string
	 */
	public function key(): string {
		return 'foundation';
	}

	/**
	 * Register explicit core services.
	 *
	 * @param ServiceContainer $container Composition target.
	 */
	public function register( ServiceContainer $container ): void {
		$container->set( EnvironmentChecker::class, static fn () => new EnvironmentChecker() );
	}
}
