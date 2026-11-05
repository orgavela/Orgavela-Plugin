<?php
/**
 * Minimal lifecycle kernel.
 *
 * @package UOP
 */

namespace UOP\Core;

use UOP\Extension\ModuleRegistry;

/** Initializes composition once, without product features. */
final class Kernel {
	/**
	 * Internal state.
	 *
	 * @var bool Whether composition has completed.
	 */
	private bool $booted = false;

	/**
	 * Accept explicit dependencies.
	 *
	 * @param ServiceContainer $container Service composition.
	 * @param ModuleRegistry   $modules Module composition.
	 */
	public function __construct( private ServiceContainer $container, private ModuleRegistry $modules ) {}

	/** Register services once. */
	public function boot(): void {
		if ( ! $this->booted ) {
			$this->modules->register( $this->container );
			$this->booted = true;
		}
	}
}
