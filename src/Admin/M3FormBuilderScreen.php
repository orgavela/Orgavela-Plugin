<?php
/**
 * M3 minimal keyboard-accessible React form builder host.
 *
 * @package UOP
 */

namespace UOP\Admin;

use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Domain\Organization\OrgScope;

/** Admin markup is only the mounting point; commands use M3 REST services. */
final class M3FormBuilderScreen {
	/**
	 * Bind the central authorization boundary.
	 *
	 * @param PolicyService $policy M2 organization policy.
	 */
	public function __construct( private PolicyService $policy ) {}

	/** Register the dedicated admin editor screen. */
	public function menu(): void {
		add_menu_page(
			__( 'UOP Forms', 'uop-core' ),
			__( 'UOP Forms', 'uop-core' ),
			'uop_manage_forms', // phpcs:ignore -- Custom capability provisioned by the UOP CapabilityRegistry.
			'uop-forms',
			array( $this, 'render' ),
			'dashicons-feedback',
			28
		);
	}

	/**
	 * Render only for an authorized organization manager.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'You cannot manage forms in this organization.', 'uop-core' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'UOP Form Builder', 'uop-core' ) . '</h1>';
		echo '<p>' . esc_html__( 'Form drafts can be edited. Published versions remain unchanged.', 'uop-core' ) . '</p>';
		echo '<div id="uop-m3-form-builder"></div></div>';
	}

	/**
	 * Enqueue React from WordPress only on the form-builder admin page.
	 *
	 * @param string $hook Active administration hook.
	 */
	public function assets( string $hook ): void {
		if ( 'toplevel_page_uop-forms' !== $hook || ! $this->can_manage() ) {
			return;
		}
		$url     = plugin_dir_url( dirname( __DIR__, 2 ) . '/uop-core.php' );
		$version = defined( 'UOP_CORE_VERSION' ) ? (string) constant( 'UOP_CORE_VERSION' ) : '0.1.0-alpha.2';
		wp_enqueue_style( 'uop-m3-form-builder', $url . 'assets/m3-form-builder.css', array(), $version );
		wp_enqueue_script(
			'uop-m3-form-builder',
			$url . 'assets/m3-form-builder.js',
			array( 'wp-element', 'wp-api-fetch', 'wp-i18n' ),
			$version,
			true
		);
		wp_add_inline_script(
			'uop-m3-form-builder',
			'window.uopM3Builder = ' . wp_json_encode(
				array(
					'nonce' => wp_create_nonce( 'wp_rest' ),
				),
				JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
			) . ';',
			'before'
		);
	}

	/**
	 * Combine WordPress capability and current organization assignment policy.
	 *
	 * @return bool
	 */
	private function can_manage(): bool {
		$org_id = (int) get_option( 'uop_default_organization_id', 0 );
		if ( $org_id < 1 || ! current_user_can( 'uop_manage_forms' ) ) { // phpcs:ignore -- Custom UOP capability.
			return false;
		}
		$scope = new OrgScope( $org_id );
		return $this->policy->can( new Actor( get_current_user_id() ), 'form.manage', new PolicyObject( $scope->id, 'organization', $scope->id ) )->allowed;
	}
}
