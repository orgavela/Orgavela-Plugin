<?php
/**
 * Module composition contract, without feature persistence or UI.
 *
 * @package UOP
 */

namespace UOP\Extension;

use UOP\Core\ServiceContainer;

/** Explicit module contract. */
interface ModuleInterface {
	/**
	 * Get unique module identifier.
	 *
	 * @return string
	 */
	public function key(): string;
	/**
	 * Register services.
	 *
	 * @param ServiceContainer $container Composition target.
	 */
	public function register( ServiceContainer $container ): void;
}
