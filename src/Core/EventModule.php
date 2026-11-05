<?php
/**
 * WordPress event CPT lifecycle registration.
 *
 * @package UOP
 */

namespace UOP\Core;

use UOP\Extension\ModuleInterface;

/** The WordPress post only stores editorial event content. */
final class EventModule implements ModuleInterface {
	/**
	 * Stable module key.
	 *
	 * @return string
	 */
	public function key(): string {
		return 'events';
	}

	/**
	 * Register the CPT at the required WordPress init boundary.
	 *
	 * @param ServiceContainer $container Kernel composition.
	 */
	public function register( ServiceContainer $container ): void {
		add_action( 'init', array( self::class, 'register_type' ) );
	}

	/** Register editorial event posts, without duplicating operational post meta. */
	public static function register_type(): void {
		register_post_type(
			'uop_event',
			array(
				'labels'          => array(
					'name'          => __( 'Events', 'uop-core' ),
					'singular_name' => __( 'Event', 'uop-core' ),
				),
				'public'          => true,
				'show_in_rest'    => true,
				'has_archive'     => false,
				'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}
}
