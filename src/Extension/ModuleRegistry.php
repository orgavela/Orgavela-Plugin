<?php
/**
 * Core extension registry.
 *
 * @package UOP
 */

namespace UOP\Extension;

use LogicException;
use UOP\Core\ServiceContainer;

/** Registers each module exactly once. */
final class ModuleRegistry {
	/**
	 * Internal state.
	 *
	 * @var array<string, ModuleInterface>
	 */
	private array $modules = array();

	/**
	 * Add a module.
	 *
	 * @param ModuleInterface $module Module.
	 * @throws LogicException For a duplicate module.
	 */
	public function add( ModuleInterface $module ): void {
		if ( isset( $this->modules[ $module->key() ] ) ) {
			throw new LogicException( 'Duplicate module.' );
		}
		$this->modules[ $module->key() ] = $module;
	}

	/**
	 * Compose registered modules.
	 *
	 * @param ServiceContainer $container Target.
	 */
	public function register( ServiceContainer $container ): void {
		foreach ( $this->modules as $module ) {
			$module->register( $container );
		}
	}
}
