<?php
/**
 * WordPress primitive capabilities and default role bundles.
 *
 * @package UOP
 */

namespace UOP\Application\Policy;

/** Role names are provisioning conveniences, never authorization predicates. */
final class CapabilityRegistry {
	/**
	 * List primitive WordPress capabilities supported by UOP.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			'uop_manage_settings',
			'uop_manage_organization',
			'uop_view_people',
			'uop_edit_people',
			'uop_manage_delegations',
			'uop_manage_events',
			'uop_manage_forms',
			'uop_view_registrations',
			'uop_review_registrations',
			'uop_manage_capacity',
			'uop_send_communications',
			'uop_export_data',
			'uop_view_sensitive_data',
			'uop_edit_sensitive_data',
			'uop_manage_privacy',
			'uop_view_audit',
		);
	}

	/**
	 * Define default capability bundles for provisioning.
	 *
	 * @return array<string, list<string>>
	 */
	public static function roles(): array {
		return array(
			'org_manager'   => array_values( array_diff( self::all(), array( 'uop_manage_settings' ) ) ),
			'event_manager' => array( 'uop_view_people', 'uop_edit_people', 'uop_manage_events', 'uop_manage_forms', 'uop_view_registrations', 'uop_review_registrations', 'uop_manage_capacity', 'uop_send_communications', 'uop_export_data' ),
			'staff'         => array( 'uop_view_people', 'uop_edit_people', 'uop_view_registrations' ),
			'viewer'        => array( 'uop_view_people', 'uop_view_registrations' ),
			'participant'   => array(),
		);
	}

	/**
	 * Install WordPress capability bundles once per site.
	 */
	public static function install(): void {
		if ( 1 === (int) get_option( 'uop_caps_version', 0 ) ) {
			return;
		}
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::all() as $capability ) {
				$admin->add_cap( $capability );
			}
		}
		foreach ( self::roles() as $role_key => $capabilities ) {
			$slug = 'uop_' . $role_key;
			add_role( $slug, 'UOP ' . ucwords( str_replace( '_', ' ', $role_key ) ), array( 'read' => true ) );
			$role = get_role( $slug );
			if ( $role ) {
				foreach ( $capabilities as $capability ) {
					$role->add_cap( $capability );
				}
			}
		}
		update_option( 'uop_caps_version', 1, false );
	}
}
