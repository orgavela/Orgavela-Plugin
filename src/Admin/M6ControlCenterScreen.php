<?php
/**
 * M6-05 administrator control center, audit and safe schema status.
 *
 * @package UOP
 */

namespace UOP\Admin;

use UOP\Application\Policy\Actor;
use UOP\Application\Query\M6OperationsReadService;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\Installer;
use UOP\Infrastructure\Database\M5OperationsRepository;
use UOP\Infrastructure\Queue\GuardianQuotaScheduleHealth;

/** Existing M3/M5 commands remain their own source of truth and permissions. */
final class M6ControlCenterScreen {
	/**
	 * Compose already permission-filtered operational readers.
	 *
	 * @param M6OperationsReadService $reads      Live organization policy.
	 * @param M5OperationsRepository  $operations M5 minimized diagnostic counts.
	 */
	public function __construct(
		private M6OperationsReadService $reads,
		private M5OperationsRepository $operations
	) {}

	/** Advertise separate navigation and audit pages with minimal capabilities. */
	public function menu(): void {
		add_menu_page(
			__( 'UOP Overview', 'uop-core' ),
			__( 'UOP Overview', 'uop-core' ),
			'uop_manage_settings', // phpcs:ignore -- Installed UOP capability.
			'uop-overview',
			array( $this, 'overview' ),
			'dashicons-dashboard',
			27
		);
		add_menu_page(
			__( 'UOP Audit', 'uop-core' ),
			__( 'UOP Audit', 'uop-core' ),
			'uop_view_audit', // phpcs:ignore -- Installed UOP capability.
			'uop-audit',
			array( $this, 'audit' ),
			'dashicons-visibility',
			32
		);
	}

	/**
	 * Enqueue layout on the two new screens and existing M5 operations.
	 *
	 * @param string $hook Active WordPress admin page.
	 */
	public function assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'toplevel_page_uop-overview', 'toplevel_page_uop-audit', 'toplevel_page_uop-operations' ), true ) ) {
			return;
		}
		$scope = $this->scope();
		$actor = new Actor( get_current_user_id() );
		if ( null === $scope ) {
			return;
		}
		if ( 'toplevel_page_uop-audit' === $hook && ! $this->can_audit( $actor, $scope ) ) {
			return;
		}
		if ( 'toplevel_page_uop-overview' === $hook && ! $this->can_overview( $actor, $scope ) ) {
			return;
		}
		// Operations is available to authorized mail, privacy or export staff,
		// not just organization-overview managers. Match its own access policy
		// so a legitimate operator never receives an unstyled, overflowing UI.
		if ( 'toplevel_page_uop-operations' === $hook
			&& ! ( $this->allowed( $actor, $scope, 'communication.send', 'uop_manage_settings' )
				|| $this->allowed( $actor, $scope, 'privacy.manage', 'uop_manage_settings' )
				|| $this->allowed( $actor, $scope, 'export.create', 'uop_manage_settings' ) ) ) {
			return;
		}
		$url     = plugin_dir_url( dirname( __DIR__, 2 ) . '/uop-core.php' );
		$version = defined( 'UOP_CORE_VERSION' ) ? (string) constant( 'UOP_CORE_VERSION' ) : '0.1.0-alpha.2';
		wp_enqueue_style( 'uop-m6-console', $url . 'assets/m6-console.css', array(), $version );
	}

	/** Render manager-only links and tenant-owned diagnostic summaries. */
	public function overview(): void {
		$scope = $this->scope();
		$actor = new Actor( get_current_user_id() );
		if ( null === $scope || ! $this->can_overview( $actor, $scope ) ) {
			wp_die( esc_html__( 'You cannot access this overview.', 'uop-core' ) );
		}
		echo '<div class="wrap uop-m6-console"><h1>' . esc_html__( 'UOP Control Center', 'uop-core' ) . '</h1>';
		echo '<p>' . esc_html__( 'Manage organization workflows and review their current health. Each destination rechecks its own permissions.', 'uop-core' ) . '</p>';
		echo '<nav class="uop-m6-console__nav" aria-label="' . esc_attr__( 'UOP Administration', 'uop-core' ) . '">';
		$this->link( $actor, $scope, 'form.manage', 'uop_manage_forms', 'uop-forms', __( 'Form builder', 'uop-core' ) );
		$this->link( $actor, $scope, 'person.view', 'uop_view_people', 'uop-people', __( 'People', 'uop-core' ) );
		$this->link( $actor, $scope, 'delegation.manage', 'uop_manage_delegations', 'uop-guardians', __( 'Guardian requests', 'uop-core' ) );
		$this->link( $actor, $scope, 'registration.view', 'uop_view_registrations', 'uop-registrations', __( 'Registrations', 'uop-core' ) );
		$this->link( $actor, $scope, 'communication.send', 'uop_manage_settings', 'uop-operations', __( 'Communication and privacy', 'uop-core' ) );
		$this->link( $actor, $scope, 'privacy.manage', 'uop_manage_privacy', 'uop-consents', __( 'Consent documents', 'uop-core' ) );
		$this->link( $actor, $scope, 'audit.view', 'uop_view_audit', 'uop-audit', __( 'Audit trail', 'uop-core' ) );
		echo '</nav><div class="uop-m6-console__grid">';

		if ( $this->allowed( $actor, $scope, 'form.manage', 'uop_manage_forms' ) ) {
			$this->heading( __( 'Forms', 'uop-core' ), __( 'Drafts, validation and immutable published form versions are managed in the existing builder.', 'uop-core' ) );
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=uop-forms' ) ) . '">' . esc_html__( 'Open form builder', 'uop-core' ) . '</a></p></section>';
		}
		if ( $this->allowed( $actor, $scope, 'communication.send', 'uop_send_communications' ) ) {
			$this->heading( __( 'Email delivery', 'uop-core' ), __( 'Failed and ambiguous states require separate handling. No recipients or message content are displayed.', 'uop-core' ) );
			$this->counts( $this->operations->counts( $scope, 'email' ) );
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=uop-operations#uop-mail' ) ) . '">' . esc_html__( 'Review delivery', 'uop-core' ) . '</a></p></section>';
		}
		if ( $this->allowed( $actor, $scope, 'privacy.manage', 'uop_manage_privacy' ) ) {
			$rules    = $this->operations->retention_rules( $scope );
			$failures = $this->operations->retention_failures( $scope );
			$active   = count( array_filter( $rules, static fn ( array $r ): bool => 1 === (int) $r['enabled'] ) );
			$this->heading( __( 'Privacy and retention', 'uop-core' ), __( 'Manual subject verification and preview-only retention are available in Operations.', 'uop-core' ) );
			/* translators: %d: Number of active configured retention rules. */
			echo '<p>' . esc_html( sprintf( __( 'Active retention rules: %d', 'uop-core' ), $active ) ) . '</p>';
			/* translators: %d: Number of failed durable outbox categories. */
			echo '<p>' . esc_html( sprintf( __( 'Retention recovery categories: %d', 'uop-core' ), count( $failures ) ) ) . '</p>';
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=uop-operations#uop-privacy' ) ) . '">' . esc_html__( 'Open privacy operations', 'uop-core' ) . '</a></p>';
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=uop-consents' ) ) . '">' . esc_html__( 'Manage consent documents', 'uop-core' ) . '</a></p></section>';
		}
		if ( $this->allowed( $actor, $scope, 'export.create', 'uop_export_data' ) ) {
			$this->heading( __( 'Private exports', 'uop-core' ), __( 'Export jobs are restricted, time-limited and never downloadable from this overview.', 'uop-core' ) );
			$this->counts( $this->operations->counts( $scope, 'export' ) );
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=uop-operations#uop-exports' ) ) . '">' . esc_html__( 'Open export operations', 'uop-core' ) . '</a></p></section>';
		}
		if ( $this->can_audit( $actor, $scope ) ) {
			$this->heading( __( 'Audit', 'uop-core' ), __( 'The audit view excludes actor identifiers, private request data and tokens.', 'uop-core' ) );
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=uop-audit' ) ) . '">' . esc_html__( 'View recent audit events', 'uop-core' ) . '</a></p></section>';
		}
		$this->heading( __( 'System health', 'uop-core' ), __( 'The existing WordPress Site Health schema check remains authoritative.', 'uop-core' ) );
		$health = Installer::health();
		echo '<p role="status"><strong>' . esc_html( (string) $health['label'] ) . '</strong></p>';
		// Managers see only coarse queue health: no action IDs, people or tokens.
		$quota_state  = GuardianQuotaScheduleHealth::inspect( time() );
		$quota_labels = array(
			'ok'          => __( 'Guardian quota cleanup: scheduled and previously completed.', 'uop-core' ),
			'first_run'   => __( 'Guardian quota cleanup: scheduled, first run pending.', 'uop-core' ),
			'missing'     => __( 'Guardian quota cleanup: scheduled action missing.', 'uop-core' ),
			'duplicate'   => __( 'Guardian quota cleanup: multiple pending actions detected.', 'uop-core' ),
			'overdue'     => __( 'Guardian quota cleanup: scheduled action is overdue.', 'uop-core' ),
			'gap'         => __( 'Guardian quota cleanup: next action is unusually far away.', 'uop-core' ),
			'failed'      => __( 'Guardian quota cleanup: a failed action needs operator review.', 'uop-core' ),
			'unavailable' => __( 'Guardian quota cleanup: scheduler status unavailable.', 'uop-core' ),
		);
		$quota_label  = $quota_labels[ $quota_state ] ?? $quota_labels['unavailable'];
		echo '<p role="status"><strong>' . esc_html( $quota_label ) . '</strong></p>';
		if ( 'ok' !== $quota_state ) {
			echo '<p class="uop-m6-console__warning">' . esc_html__( 'Review Action Scheduler status before relying on automated retention. This indicator does not verify an external cron trigger.', 'uop-core' ) . '</p>';
		}
		if ( 'good' !== $health['status'] ) {
			echo '<p class="uop-m6-console__warning">' . esc_html__( 'Database schema or migration state needs attention. Review WordPress Site Health before changing settings.', 'uop-core' ) . '</p>';
		}
		if ( current_user_can( 'view_site_health_checks' ) ) {
			echo '<p><a href="' . esc_url( admin_url( 'site-health.php' ) ) . '">' . esc_html__( 'Open WordPress Site Health', 'uop-core' ) . '</a></p>';
		}
		echo '</section></div></div>';
	}

	/** Render the immutable, scope-checked, payload-free audit view. */
	public function audit(): void {
		$scope = $this->scope();
		$actor = new Actor( get_current_user_id() );
		if ( null === $scope || ! $this->can_audit( $actor, $scope ) ) {
			wp_die( esc_html__( 'You cannot view the audit trail.', 'uop-core' ) );
		}
		$feed = $this->reads->audit( $actor, $scope );
		if ( null === $feed ) {
			wp_die( esc_html__( 'You cannot view the audit trail.', 'uop-core' ) );
		}
		echo '<div class="wrap uop-m6-console"><h1>' . esc_html__( 'UOP Audit', 'uop-core' ) . '</h1>';
		echo '<p>' . esc_html__( 'The latest 50 audit events are read-only. Actor identifiers, object IDs, original values and private payloads are not displayed.', 'uop-core' ) . '</p>';
		if ( empty( $feed['items'] ) ) {
			echo '<p role="status">' . esc_html__( 'There are no audit events in this organization yet.', 'uop-core' ) . '</p>';
		} else {
			echo '<div class="uop-m6-console__table" role="region" tabindex="0" aria-label="' . esc_attr__( 'Audit events, horizontally scrollable', 'uop-core' ) . '"><table class="widefat striped"><thead><tr>';
			foreach ( array( __( 'Time (UTC)', 'uop-core' ), __( 'Action', 'uop-core' ), __( 'Resource', 'uop-core' ), __( 'Result', 'uop-core' ), __( 'Correlation', 'uop-core' ) ) as $header ) {
				echo '<th scope="col">' . esc_html( $header ) . '</th>';
			}
			echo '</tr></thead><tbody>';
			foreach ( $feed['items'] as $item ) {
				echo '<tr><td>' . esc_html( $item['occurred_at'] ) . '</td><td>' . esc_html( $item['action'] ) . '</td><td>' . esc_html( $item['object_type'] ) . '</td><td>' . esc_html( $item['result'] ) . '</td><td><code>' . esc_html( $item['correlation'] ) . '</code></td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</div>';
	}

	/**
	 * Return the current trusted site organization when configured.
	 *
	 * @return OrgScope|null
	 */
	private function scope(): ?OrgScope {
		$id = (int) get_option( 'uop_default_organization_id', 0 );
		return $id > 0 ? new OrgScope( $id ) : null;
	}

	/**
	 * Validate an action and WordPress capability without widening M2 rights.
	 *
	 * @param Actor    $actor Current account.
	 * @param OrgScope $scope Trusted tenant.
	 * @param string   $action Existing policy action.
	 * @param string   $cap    Existing primitive capability.
	 * @return bool
	 */
	private function allowed( Actor $actor, OrgScope $scope, string $action, string $cap ): bool {
		return current_user_can( $cap ) && $this->reads->allowed( $actor, $scope, $action );
	}

	/**
	 * Allow only UOP configuration administrators to see system status.
	 *
	 * @param Actor    $actor Current account.
	 * @param OrgScope $scope Trusted tenant.
	 * @return bool
	 */
	private function can_overview( Actor $actor, OrgScope $scope ): bool {
		return $this->allowed( $actor, $scope, 'organization.manage', 'uop_manage_settings' );
	}

	/**
	 * Gate audit to explicitly assigned staff; no general access to health.
	 *
	 * @param Actor    $actor Current account.
	 * @param OrgScope $scope Trusted tenant.
	 * @return bool
	 */
	private function can_audit( Actor $actor, OrgScope $scope ): bool {
		return $this->allowed( $actor, $scope, 'audit.view', 'uop_view_audit' );
	}

	/**
	 * Link only to a page for which the user has live authorization.
	 *
	 * @param Actor    $actor Current account.
	 * @param OrgScope $scope Trusted tenant.
	 * @param string   $action Required policy.
	 * @param string   $cap    Required WordPress capability.
	 * @param string   $page   Fixed admin page slug.
	 * @param string   $label  Human label.
	 */
	private function link( Actor $actor, OrgScope $scope, string $action, string $cap, string $page, string $label ): void {
		if ( $this->allowed( $actor, $scope, $action, $cap ) ) {
			echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . $page ) ) . '">' . esc_html( $label ) . '</a> ';
		}
	}

	/**
	 * Render a semantic overview section with text and link content below.
	 *
	 * @param string $title Accessible heading.
	 * @param string $note  Safe explanatory content.
	 */
	private function heading( string $title, string $note ): void {
		echo '<section class="uop-m6-console__panel"><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $note ) . '</p>';
	}

	/**
	 * Render only status count keys and aggregate counts.
	 *
	 * @param array<string,int> $counts Approved operational state counts.
	 */
	private function counts( array $counts ): void {
		if ( ! $counts ) {
			echo '<p role="status">' . esc_html__( 'No records in this section.', 'uop-core' ) . '</p>';
			return;
		}
		echo '<dl class="uop-m6-console__stats">';
		foreach ( $counts as $status => $count ) {
			echo '<div><dt>' . esc_html( $status ) . '</dt><dd>' . esc_html( (string) $count ) . '</dd></div>';
		}
		echo '</dl>';
	}
}
