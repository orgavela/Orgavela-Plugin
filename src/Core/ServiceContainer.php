<?php
/**
 * Explicit lazy service composition.
 *
 * @package UOP
 */

namespace UOP\Core;

use Closure;
use LogicException;

/** Registry with cycle detection and no reflection autowiring. */
final class ServiceContainer {
	/**
	 * Internal state.
	 *
	 * @var array<string, Closure(self): object>
	 */
	private array $factories = array();
	/**
	 * Internal state.
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();
	/**
	 * Internal state.
	 *
	 * @var array<string, bool>
	 */
	private array $resolving = array();

	/**
	 * Register a factory once.
	 *
	 * @param string  $id Service identifier.
	 * @param Closure $factory Explicit factory.
	 * @phpstan-param Closure(self): object $factory
	 * @throws LogicException If already registered.
	 */
	public function set( string $id, Closure $factory ): void {
		if ( isset( $this->factories[ $id ] ) ) {
			throw new LogicException( 'Service already registered.' );
		}
		$this->factories[ $id ] = $factory;
	}

	/**
	 * Resolve one shared service.
	 *
	 * @param string $id Identifier.
	 * @return object
	 * @throws LogicException If unknown or circular.
	 */
	public function get( string $id ): object {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}
		if ( ! isset( $this->factories[ $id ] ) || isset( $this->resolving[ $id ] ) ) {
			throw new LogicException( 'Unknown or circular service.' );
		}
		$this->resolving[ $id ] = true;
		try {
			$this->instances[ $id ] = ( $this->factories[ $id ] )( $this );
			return $this->instances[ $id ];
		} finally {
			unset( $this->resolving[ $id ] );
		}
	}
}
