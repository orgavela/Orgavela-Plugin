<?php
/**
 * Guardian request/review contract against real WordPress REST and InnoDB.
 *
 * @package UOP
 */

namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use UOP\Application\Event\EventService;
use UOP\Application\Identity\GuardianReviewService;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Query\M6ReadService;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Core\TransactionManager;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\AssignmentRepository;
use UOP\Infrastructure\Database\AuditWriter;
use UOP\Infrastructure\Database\DatabaseException;
use UOP\Infrastructure\Database\DelegationRepository;
use UOP\Infrastructure\Database\GuardianClaimQuotaRepository;
use UOP\Infrastructure\Database\GuardianClaimQuotaPruner;
use UOP\Infrastructure\Database\EventRepository;
use UOP\Infrastructure\Database\Installer;
use UOP\Infrastructure\Database\OccurrenceRepository;
use UOP\Infrastructure\Database\OutboxRepository;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\RelationshipRepository;
use UOP\Infrastructure\Database\RegistrationReadRepository;
use UOP\Infrastructure\Database\SchemaManifest;
use UOP\Infrastructure\Database\WpdbConnection;
use UOP\REST\GuardianReviewController;

/** A claimed guardian relationship must not authorize itself. */
final class M610GuardianReviewTest extends TestCase {
	private WpdbConnection $db;
	private OrgScope $scope;
	private string $prefix;

	/** Isolate every full workflow from other WordPress integration tests. */
	protected function setUp(): void {
		global $wpdb;
		$this->db     = new WpdbConnection( $wpdb );
		$this->prefix = $wpdb->prefix . 'uop_';
		$manifest = new SchemaManifest( dirname( __DIR__, 2 ) . '/schema/manifest.json' );
		foreach ( array_keys( $manifest->tables() ) as $table ) {
			$this->db->execute( 'DROP TABLE IF EXISTS %i', array( $this->prefix . $table ) );
		}
		foreach ( array( 'uop_db_version', 'uop_data_version', 'uop_migration_progress_1', 'uop_migration_status', 'uop_default_organization_id' ) as $key ) {
			delete_option( $key );
		}
		Installer::runner()->run();
		$this->scope = new OrgScope( (int) get_option( 'uop_default_organization_id' ) );
		wp_set_current_user( 0 );
	}

	/** Create two real independent actors and no implicit family rights. */
	private function fixture(): array {
		global $wpdb;
		if ( ! post_type_exists( 'uop_event' ) ) {
			register_post_type( 'uop_event', array( 'public' => true ) );
		}
		$admin = wp_create_user( 'uop_gadmin_' . bin2hex( random_bytes( 4 ) ), wp_generate_password( 25 ), 'admin-' . bin2hex( random_bytes( 4 ) ) . '@example.invalid' );
		$parent = wp_create_user( 'uop_gparent_' . bin2hex( random_bytes( 4 ) ), wp_generate_password( 25 ), 'parent-' . bin2hex( random_bytes( 4 ) ) . '@example.invalid' );
		$stranger = wp_create_user( 'uop_gother_' . bin2hex( random_bytes( 4 ) ), wp_generate_password( 25 ), 'other-' . bin2hex( random_bytes( 4 ) ) . '@example.invalid' );
		self::assertIsInt( $admin );
		self::assertIsInt( $parent );
		self::assertIsInt( $stranger );
		( new \WP_User( $admin ) )->set_role( 'administrator' );
		( new \WP_User( $parent ) )->set_role( 'subscriber' );
		( new \WP_User( $stranger ) )->set_role( 'subscriber' );
		$people = new PersonRepository( $this->db, $this->prefix );
		$relationships = new RelationshipRepository( $this->db, $this->prefix );
		$delegations = new DelegationRepository( $this->db, $this->prefix );
		$policy = new PolicyService( $people, $delegations, new AssignmentRepository( $this->db, $this->prefix ), static fn ( int $id, string $cap ): bool => $id === $admin );
		$tx = new TransactionManager( $this->db, static function ( int $n ): void {}, static function ( \Throwable $e ): void {} );
		$audit = new AuditWriter( $this->db, $this->prefix );
		$outbox = new OutboxRepository( $this->db, $this->prefix );
		$reviews = new GuardianReviewService( $people, $relationships, $delegations, $this->db, $policy, $tx, $audit, $outbox, $this->prefix, new GuardianClaimQuotaRepository( $this->db, $wpdb->options ) );
		( new GuardianReviewController( $reviews ) )->register();

		$now = gmdate( 'Y-m-d H:i:s' );
		$parent_person = PublicId::generate();
		$child = PublicId::generate();
		$other_child = PublicId::generate();
		$people->create( $this->scope, $parent_person, 'Caregiver', 'shared-family@example.invalid', $now );
		$people->create( $this->scope, $child, 'Dependent', 'shared-family@example.invalid', $now );
		$people->create( $this->scope, $other_child, 'Another dependent', 'shared-family@example.invalid', $now );
		self::assertTrue( $people->link( $this->scope, $parent_person, $parent, $now ) );
		$post = wp_insert_post( array( 'post_type' => 'uop_event', 'post_status' => 'publish', 'post_title' => 'CI child event' ), true );
		self::assertIsInt( $post );
		$event_id = ( new EventService( new EventRepository( $this->db, $this->prefix ), new OccurrenceRepository( $this->db, $this->prefix ), $policy, $tx, $audit, $outbox ) )
			->configure( new Actor( $admin ), $this->scope, $post, 'Europe/Berlin', $now, CorrelationId::generate() );
		$this->db->execute(
			'UPDATE %i SET visibility = %s WHERE organization_id = %d AND public_id = %s',
			array( $this->prefix . 'event_settings', 'public', $this->scope->id, $event_id->to_binary() )
		);
		delete_transient( 'uop_guardian_claim_' . hash( 'sha256', $this->scope->id . ':' . $parent ) );
		return compact( 'admin', 'parent', 'stranger', 'people', 'relationships', 'delegations', 'policy', 'reviews', 'child', 'other_child', 'post', 'event_id' );
	}

	/** Send untrusted JSON to actual WordPress REST dispatch, not a mock. */
	private function json( string $method, string $route, array $body ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );
		return rest_do_request( $request );
	}

	/** Count tenant-owned rows to prove denied operations are side-effect free. */
	private function tenant_count( string $table ): int {
		$rows = $this->db->rows(
			'SELECT COUNT(*) AS n FROM %i WHERE organization_id = %d',
			array( $this->prefix . $table, $this->scope->id )
		);
		return (int) $rows[0]['n'];
	}

	/** Exercise anonymity, two-person review, event scope, expiry and revocation. */
	public function test_guardian_request_requires_verified_review_and_is_not_implicit_authority(): void {
		$s = $this->fixture();
		$endpoint = '/uop/v1/me/guardian-claims';
		wp_set_current_user( 0 );
		self::assertSame( 401, $this->json( 'POST', $endpoint, array( 'subject_id' => $s['child']->to_string() ) )->get_status() );
		self::assertSame( 0, $this->tenant_count( 'relationships' ) );
		wp_set_current_user( $s['parent'] );
		$unknown = PublicId::generate();
		$no_match = $this->json( 'POST', $endpoint, array( 'subject_id' => $unknown->to_string() ) );
		self::assertSame( 202, $no_match->get_status() );
		self::assertSame( 0, $this->tenant_count( 'relationships' ) );
		$receipt = $this->json( 'POST', $endpoint, array( 'subject_id' => $s['child']->to_string() ) );
		self::assertSame( 202, $receipt->get_status() );
		self::assertSame( $no_match->get_data(), $receipt->get_data(), 'Unknown persons cannot be enumerated through claim receipts.' );
		self::assertSame( 1, $this->tenant_count( 'relationships' ) );
		self::assertSame( 0, $this->tenant_count( 'delegations' ) );
		$child_id = (int) $s['people']->find( $this->scope, $s['child'] )['id'];
		$reads = new M6ReadService(
			$s['people'],
			$s['delegations'],
			new RegistrationReadRepository( $this->db, $this->prefix ),
			$s['policy'],
			new EventRepository( $this->db, $this->prefix )
		);
		$before_scope = $reads->registration_subjects_for_event( new Actor( $s['parent'] ), $this->scope, $s['event_id'] );
		self::assertCount( 1, $before_scope );
		self::assertNotContains( $s['child']->to_string(), array_column( $before_scope, 'public_id' ) );
		$resource = new PolicyObject( $this->scope->id, 'registration', $child_id, $child_id, $s['post'] );
		self::assertFalse( $s['policy']->can( new Actor( $s['parent'] ), 'registration.create', $resource )->allowed );
		self::assertSame( 403, rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/admin/guardian-claims' ) )->get_status() );
		$before = $this->tenant_count( 'audit_log' );
		self::assertSame( 403, $this->json( 'POST', '/uop/v1/admin/guardian-claims/' . $unknown->to_string() . '/review', array( 'decision' => 'approve', 'evidence_checked' => true, 'event_post_id' => $s['post'] ) )->get_status() );
		self::assertSame( $before, $this->tenant_count( 'audit_log' ) );

		wp_set_current_user( $s['admin'] );
		$queue = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/admin/guardian-claims' ) );
		self::assertSame( 200, $queue->get_status() );
		self::assertCount( 1, $queue->get_data()['items'] );
		self::assertStringNotContainsString( '@example.invalid', wp_json_encode( $queue->get_data() ) );
		$claim_id = $queue->get_data()['items'][0]['public_id'];
		$path = '/uop/v1/admin/guardian-claims/' . $claim_id . '/review';
		$invalid = $this->json( 'POST', $path, array( 'decision' => 'approve', 'evidence_checked' => false, 'event_post_id' => $s['post'] ) );
		self::assertSame( 400, $invalid->get_status() );
		self::assertSame( 0, $this->tenant_count( 'delegations' ) );
		self::assertSame( 'pending', $s['relationships']->find( $this->scope, PublicId::from_string( $claim_id ) )['status'] );
		$forged = $this->json( 'POST', $path, array( 'decision' => 'approve', 'evidence_checked' => true, 'event_post_id' => $s['post'], 'actor_user_id' => $s['parent'] ) );
		self::assertSame( 400, $forged->get_status() );
		$wrong_scope = $this->json( 'POST', $path, array( 'decision' => 'approve', 'evidence_checked' => true, 'event_post_id' => 99999999 ) );
		self::assertSame( 409, $wrong_scope->get_status() );
		self::assertSame( 0, $this->tenant_count( 'delegations' ) );

		$approved = $this->json( 'POST', $path, array( 'decision' => 'approve', 'evidence_checked' => true, 'event_post_id' => $s['post'] ) );
		self::assertSame( 200, $approved->get_status(), wp_json_encode( $approved->get_data() ) );
		self::assertSame( 'approved', $approved->get_data()['status'] );
		self::assertSame( 'active', $s['relationships']->find( $this->scope, PublicId::from_string( $claim_id ) )['status'] );
		self::assertSame( 1, $this->tenant_count( 'delegations' ) );
		$grant = $this->db->rows( 'SELECT scope_type, scope_id, valid_to, relationship_id FROM %i WHERE organization_id = %d', array( $this->prefix . 'delegations', $this->scope->id ) )[0];
		self::assertSame( 'event', $grant['scope_type'] );
		self::assertSame( $s['post'], (int) $grant['scope_id'] );
		self::assertNotNull( $grant['valid_to'] );
		self::assertTrue( $s['policy']->can( new Actor( $s['parent'] ), 'registration.create', $resource )->allowed );
		$authorized = $reads->registration_subjects_for_event( new Actor( $s['parent'] ), $this->scope, $s['event_id'] );
		self::assertCount( 2, $authorized );
		self::assertContains( $s['child']->to_string(), array_column( $authorized, 'public_id' ) );
		self::assertArrayNotHasKey( 'primary_email', $authorized[1] );
		self::assertCount( 1, $reads->my_people( new Actor( $s['parent'] ), $this->scope ), 'Event approval must not expose general child profile.' );
		self::assertFalse( $s['policy']->can( new Actor( $s['parent'] ), 'registration.create', new PolicyObject( $this->scope->id, 'registration', $child_id, $child_id, $s['post'] + 99 ) )->allowed );
		self::assertSame( 409, $this->json( 'POST', $path, array( 'decision' => 'approve', 'evidence_checked' => true, 'event_post_id' => $s['post'] ) )->get_status() );
		self::assertSame( 1, $this->tenant_count( 'delegations' ) );
		$this->db->execute( 'UPDATE %i SET status = %s WHERE organization_id = %d AND public_id = %s', array( $this->prefix . 'relationships', 'revoked', $this->scope->id, PublicId::from_string( $claim_id )->to_binary() ) );
		self::assertFalse( $s['policy']->can( new Actor( $s['parent'] ), 'registration.create', $resource )->allowed );
		self::assertCount( 1, $reads->registration_subjects_for_event( new Actor( $s['parent'] ), $this->scope, $s['event_id'] ) );
	}

	/**
	 * A reviewer linked to the represented subject is not independent.
	 * Their manager capability must never authorize their own representation.
	 */
	public function test_reviewer_linked_to_subject_cannot_approve_own_guardian_claim(): void {
		$s   = $this->fixture();
		$now = gmdate( 'Y-m-d H:i:s' );
		self::assertTrue( $s['people']->link( $this->scope, $s['child'], $s['admin'], $now ) );
		wp_set_current_user( $s['parent'] );
		self::assertSame(
			202,
			$this->json( 'POST', '/uop/v1/me/guardian-claims', array( 'subject_id' => $s['child']->to_string() ) )->get_status()
		);
		self::assertSame( 0, $this->tenant_count( 'delegations' ) );
		wp_set_current_user( $s['admin'] );
		$queue = rest_do_request( new \WP_REST_Request( 'GET', '/uop/v1/admin/guardian-claims' ) );
		self::assertSame( 200, $queue->get_status() );
		self::assertCount( 1, $queue->get_data()['items'] );
		$claim_id = $queue->get_data()['items'][0]['public_id'];
		$path     = '/uop/v1/admin/guardian-claims/' . $claim_id . '/review';
		$before   = $this->tenant_count( 'audit_log' );
		$approval = $this->json( 'POST', $path, array( 'decision' => 'approve', 'evidence_checked' => true, 'event_post_id' => $s['post'] ) );
		self::assertSame( 409, $approval->get_status(), 'A reviewer linked to the subject cannot approve their own guardian claim.' );
		self::assertSame( 0, $this->tenant_count( 'delegations' ) );
		self::assertSame( $before, $this->tenant_count( 'audit_log' ) );
		self::assertSame( 'pending', $s['relationships']->find( $this->scope, PublicId::from_string( $claim_id ) )['status'] );

		// A conflicted reviewer may still dismiss the stale request, never grant.
		self::assertSame(
			200,
			$this->json( 'POST', $path, array( 'decision' => 'reject', 'evidence_checked' => false, 'event_post_id' => 0 ) )->get_status()
		);
		self::assertSame( 'rejected', $s['relationships']->find( $this->scope, PublicId::from_string( $claim_id ) )['status'] );
		self::assertSame( 0, $this->tenant_count( 'delegations' ) );
	}

	/**
	 * Stale WordPress transient reads must never permit a fourth claim.
	 * This simulates multiple workers observing the same old cache value.
	 */
	public function test_guardian_quota_rejects_fourth_attempt_despite_stale_transient_reads(): void {
		global $wpdb;
		$s = $this->fixture();
		$before_audit = $this->tenant_count( 'audit_log' );
		wp_set_current_user( $s['parent'] );
		$key    = 'uop_guardian_claim_' . hash( 'sha256', $this->scope->id . ':' . $s['parent'] );
		$stale  = static fn (): int => 0;
		$target = '/uop/v1/me/guardian-claims';
		add_filter( 'pre_transient_' . $key, $stale );
		try {
			for ( $i = 0; $i < 3; ++$i ) {
				$unknown = PublicId::generate();
				self::assertSame( 202, $this->json( 'POST', $target, array( 'subject_id' => $unknown->to_string() ) )->get_status() );
				$quota_key = 'uop_gclaim_q2_' . hash( 'sha256', $this->scope->id . ':' . $s['parent'] );
				$rows      = $this->db->rows( 'SELECT option_value FROM %i WHERE option_name = %s', array( $wpdb->options, $quota_key ) );
				self::assertCount( 1, $rows, 'Claim quota must persist as a database row.' );
				self::assertStringStartsWith( ( $i + 1 ) . ':', (string) $rows[0]['option_value'], 'Attempt count must be durable after each request.' );
			}
			self::assertSame( 0, $this->tenant_count( 'relationships' ) );
			self::assertSame( 0, $this->tenant_count( 'delegations' ) );
			$before_keys = $this->db->rows(
				'SELECT option_name, option_value FROM %i WHERE option_name LIKE %s ORDER BY option_name ASC',
				array( $wpdb->options, 'uop_gclaim_q2_%' )
			);
			self::assertSame( $this->scope->id, (int) get_option( 'uop_default_organization_id' ) );
			self::assertSame( 202, $this->json( 'POST', $target, array( 'subject_id' => $s['child']->to_string() ) )->get_status() );
			$after_keys = $this->db->rows(
				'SELECT option_name, option_value FROM %i WHERE option_name LIKE %s ORDER BY option_name ASC',
				array( $wpdb->options, 'uop_gclaim_q2_%' )
			);
			self::assertSame( $before_keys, $after_keys, 'An exhausted actor must not silently switch to a different quota identity.' );
			self::assertSame( $this->scope->id, (int) get_option( 'uop_default_organization_id' ) );
			self::assertSame( $s['parent'], get_current_user_id(), 'REST actor must remain the original claimant.' );
			$after = $this->db->rows( 'SELECT option_value FROM %i WHERE option_name = %s', array( $wpdb->options, $quota_key ) );
			self::assertCount( 1, $after );
			self::assertStringStartsWith( '3:', (string) $after[0]['option_value'], 'The fourth request may not reset the active quota window.' );
			self::assertSame( 0, $this->tenant_count( 'relationships' ), 'A stale transient must not bypass the three-attempt claim quota.' );
			self::assertSame( $before_audit, $this->tenant_count( 'audit_log' ) );
			self::assertSame( 0, $this->tenant_count( 'delegations' ) );
		} finally {
			remove_filter( 'pre_transient_' . $key, $stale );
		}
	}

	/**
	 * An independent InnoDB connection cannot spend a slot while the first
	 * connection holds the actor's quota row. A timed-out peer must fail closed.
	 */
	public function test_guardian_quota_serializes_across_independent_database_connections(): void {
		global $wpdb;
		$s        = $this->fixture();
		$actor_id = (int) $s['parent'];
		$now      = time();
		$primary  = new GuardianClaimQuotaRepository( $this->db, $wpdb->options );

		// A distinct wpdb instance establishes a separate physical MySQL session.
		$peer_wpdb = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$peer_wpdb->set_prefix( $wpdb->prefix );
		$peer_db   = new WpdbConnection( $peer_wpdb );
		$peer      = new GuardianClaimQuotaRepository( $peer_db, $peer_wpdb->options );
		$peer_db->execute( 'SET SESSION innodb_lock_wait_timeout = 1' );

		$this->db->execute( 'START TRANSACTION' );
		try {
			self::assertTrue( $primary->consume( $this->scope, $actor_id, $now ) );
			$peer_db->execute( 'START TRANSACTION' );
			try {
				try {
					$peer->consume( $this->scope, $actor_id, $now );
					self::fail( 'A second SQL connection must not pass an uncommitted quota lock.' );
				} catch ( DatabaseException $error ) {
					self::assertSame( 1205, $error->getCode(), 'InnoDB must report lock-wait timeout on the contested row.' );
				}
			} finally {
				$peer_db->execute( 'ROLLBACK' );
			}
		} finally {
			$this->db->execute( 'COMMIT' );
		}

		// After the lock is released, the peer sees the committed first slot.
		$peer_db->execute( 'START TRANSACTION' );
		try {
			self::assertTrue( $peer->consume( $this->scope, $actor_id, $now ) );
			$peer_db->execute( 'COMMIT' );
		} catch ( \Throwable $error ) {
			$peer_db->execute( 'ROLLBACK' );
			throw $error;
		}
		$key  = 'uop_gclaim_q2_' . hash( 'sha256', $this->scope->id . ':' . $actor_id );
		$rows = $this->db->rows( 'SELECT option_value FROM %i WHERE option_name = %s', array( $wpdb->options, $key ) );
		self::assertCount( 1, $rows );
		self::assertStringStartsWith( '2:', (string) $rows[0]['option_value'] );
		self::assertSame( 0, $this->tenant_count( 'relationships' ), 'A quota-level contention alone cannot create a guardian relationship.' );
	}

	/**
	 * Cleanup must minimize pseudonymous retention without relaxing active
	 * admission decisions or erasing unexpected/corrupt option values.
	 */
	public function test_guardian_quota_prune_preserves_active_grace_and_corrupt_rows(): void {
		global $wpdb;
		$s     = $this->fixture();
		$now   = time();
		$repo  = new GuardianClaimQuotaPruner( $this->db, $wpdb->options );
		$keys  = array(
			'expired' => 'uop_gclaim_q2_' . hash( 'sha256', $this->scope->id . ':' . $s['parent'] ),
			'active'  => 'uop_gclaim_q2_' . hash( 'sha256', $this->scope->id . ':' . $s['admin'] ),
			'grace'   => 'uop_gclaim_q2_' . hash( 'sha256', $this->scope->id . ':' . $s['stranger'] ),
			'corrupt' => 'uop_gclaim_q2_' . hash( 'sha256', 'corrupt:' . $s['parent'] ),
			'decoy'   => 'uopXgclaimXq2X' . hash( 'sha256', 'decoy:' . $s['parent'] ),
		);
		$values = array(
			'expired' => '3:' . ( $now - 90000 ),
			'active'  => '3:' . ( $now + 1200 ),
			'grace'   => '3:' . ( $now - 300 ),
			'corrupt' => 'malformed',
			'decoy'   => '3:' . ( $now - 90000 ),
		);
		try {
			foreach ( $keys as $kind => $key ) {
				$this->db->execute(
					"INSERT INTO %i (option_name, option_value, autoload) VALUES (%s,%s,'off') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
					array( $wpdb->options, $key, $values[ $kind ] )
				);
			}
			self::assertGreaterThanOrEqual( 1, $repo->prune_expired( $now ) );
			foreach ( $keys as $kind => $key ) {
				$rows = $this->db->rows( 'SELECT option_value FROM %i WHERE option_name = %s', array( $wpdb->options, $key ) );
				if ( 'expired' === $kind ) {
					self::assertCount( 0, $rows, 'Aged-out quota should be pruned.' );
				} else {
					self::assertCount( 1, $rows, $kind . ' row must remain untouched.' );
					self::assertSame( $values[ $kind ], $rows[0]['option_value'] );
				}
			}
			$quota = new GuardianClaimQuotaRepository( $this->db, $wpdb->options );
			$tx = new TransactionManager( $this->db, static function ( int $microseconds ): void {}, static function ( \Throwable $error ): void {} );
			self::assertTrue(
				$tx->run( fn (): bool => $quota->consume( $this->scope, $s['parent'], $now ) ),
				'A prior expired quota may start a fresh window after pruning.'
			);
			self::assertFalse(
				$tx->run( fn (): bool => $quota->consume( $this->scope, $s['admin'], $now ) ),
				'An active exhausted quota must remain denied after cleanup.'
			);
			self::assertSame( 0, $this->tenant_count( 'relationships' ) );
			self::assertSame( 0, $this->tenant_count( 'delegations' ) );
		} finally {
			foreach ( $keys as $key ) {
				$this->db->execute( 'DELETE FROM %i WHERE option_name = %s', array( $wpdb->options, $key ) );
			}
		}
	}

	/**
	 * Expired limits reset only through the database; corrupt state denies.
	 * No subject identifiers or personally identifying emails enter the key.
	 */
	public function test_guardian_quota_expiry_and_corruption_fail_closed(): void {
		global $wpdb;
		$s = $this->fixture();
		wp_set_current_user( $s['parent'] );
		$key = 'uop_gclaim_q2_' . hash( 'sha256', $this->scope->id . ':' . $s['parent'] );
		$url = '/uop/v1/me/guardian-claims';
		for ( $i = 0; $i < 3; ++$i ) {
			self::assertSame( 202, $this->json( 'POST', $url, array( 'subject_id' => PublicId::generate()->to_string() ) )->get_status() );
		}
		$rows = $this->db->rows( 'SELECT option_value FROM %i WHERE option_name = %s', array( $wpdb->options, $key ) );
		self::assertCount( 1, $rows );
		self::assertMatchesRegularExpression( '/^3:[0-9]+$/', (string) $rows[0]['option_value'] );
		$this->db->execute( 'UPDATE %i SET option_value = %s WHERE option_name = %s', array( $wpdb->options, '3:1', $key ) );
		self::assertSame( 202, $this->json( 'POST', $url, array( 'subject_id' => $s['child']->to_string() ) )->get_status() );
		self::assertSame( 1, $this->tenant_count( 'relationships' ), 'Only an expired window can reset a consumed quota.' );
		$after = $this->db->rows( 'SELECT option_value FROM %i WHERE option_name = %s', array( $wpdb->options, $key ) );
		self::assertMatchesRegularExpression( '/^1:[0-9]+$/', (string) $after[0]['option_value'] );
		$before_audit = $this->tenant_count( 'audit_log' );
		$this->db->execute( 'UPDATE %i SET option_value = %s WHERE option_name = %s', array( $wpdb->options, 'malformed', $key ) );
		self::assertSame( 503, $this->json( 'POST', $url, array( 'subject_id' => $s['other_child']->to_string() ) )->get_status() );
		self::assertSame( 1, $this->tenant_count( 'relationships' ) );
		self::assertSame( $before_audit, $this->tenant_count( 'audit_log' ) );
		self::assertSame( 0, $this->tenant_count( 'delegations' ) );
	}

	/** A duplicate private claim still consumes quota without extra events. */
	public function test_guardian_duplicate_claim_attempts_consume_quota_without_second_relationship(): void {
		$s = $this->fixture();
		$before_audit = $this->tenant_count( 'audit_log' );
		wp_set_current_user( $s['parent'] );
		$url = '/uop/v1/me/guardian-claims';
		for ( $i = 0; $i < 3; ++$i ) {
			self::assertSame( 202, $this->json( 'POST', $url, array( 'subject_id' => $s['child']->to_string() ) )->get_status() );
		}
		self::assertSame( 1, $this->tenant_count( 'relationships' ) );
		self::assertSame( $before_audit + 1, $this->tenant_count( 'audit_log' ) );
		global $wpdb;
		$quota_key = 'uop_gclaim_q2_' . hash( 'sha256', $this->scope->id . ':' . $s['parent'] );
		$record = $this->db->rows( 'SELECT option_value FROM %i WHERE option_name = %s', array( $wpdb->options, $quota_key ) );
		self::assertCount( 1, $record );
		self::assertStringStartsWith( '3:', (string) $record[0]['option_value'], 'Duplicate claims must still spend one quota slot.' );
		self::assertSame( 202, $this->json( 'POST', $url, array( 'subject_id' => $s['other_child']->to_string() ) )->get_status() );
		self::assertSame( 1, $this->tenant_count( 'relationships' ) );
		self::assertSame( $before_audit + 1, $this->tenant_count( 'audit_log' ) );
		self::assertSame( 0, $this->tenant_count( 'delegations' ) );
	}

	/** The service itself must enforce quota even without REST transport. */
	public function test_guardian_quota_service_direct_call_is_independently_bounded(): void {
		$s = $this->fixture();
		$before_audit = $this->tenant_count( 'audit_log' );
		$actor = new Actor( $s['parent'] );
		$now   = gmdate( 'Y-m-d H:i:s' );
		for ( $i = 0; $i < 3; ++$i ) {
			self::assertFalse( $s['reviews']->request( $actor, $this->scope, PublicId::generate(), $now, CorrelationId::generate() ) );
		}
		self::assertFalse(
			$s['reviews']->request( $actor, $this->scope, $s['child'], $now, CorrelationId::generate() ),
			'The fourth direct request must not create a guardian relationship.'
		);
		self::assertSame( 0, $this->tenant_count( 'relationships' ) );
		self::assertSame( $before_audit, $this->tenant_count( 'audit_log' ) );
	}

	/** Reviewer rejection records a decision but never grants family access. */
	public function test_rejection_and_rate_limits_cannot_grant_family_rights(): void {
		$s = $this->fixture();
		wp_set_current_user( $s['parent'] );
		$subject = $s['child']->to_string();
		$claim = '/uop/v1/me/guardian-claims';
		self::assertSame( 202, $this->json( 'POST', $claim, array( 'subject_id' => $subject ) )->get_status() );
		self::assertSame( 202, $this->json( 'POST', $claim, array( 'subject_id' => $subject ) )->get_status(), 'Replay uses the same private receipt.' );
		self::assertSame( 1, $this->tenant_count( 'relationships' ) );
		$uuid = $this->db->rows( 'SELECT public_id FROM %i WHERE organization_id = %d', array( $this->prefix . 'relationships', $this->scope->id ) )[0]['public_id'];
		$claim_id = PublicId::from_binary( $uuid )->to_string();
		// The original requester unlinks their WordPress account before staff
		// review. Approval must fail, but staff must still be able to dismiss
		// the stale request without fabricating a new family relationship.
		self::assertSame( 1, $s['people']->unlink_user( $this->scope, $s['parent'], gmdate( 'Y-m-d H:i:s' ) ) );
		wp_set_current_user( $s['admin'] );
		$review_path = '/uop/v1/admin/guardian-claims/' . $claim_id . '/review';
		self::assertSame( 409, $this->json( 'POST', $review_path, array( 'decision' => 'approve', 'evidence_checked' => true, 'event_post_id' => $s['post'] ) )->get_status() );
		self::assertSame( 0, $this->tenant_count( 'delegations' ) );
		self::assertSame( 200, $this->json( 'POST', $review_path, array( 'decision' => 'reject', 'evidence_checked' => false, 'event_post_id' => 0 ) )->get_status() );
		self::assertSame( 'rejected', $s['relationships']->find( $this->scope, PublicId::from_string( $claim_id ) )['status'] );
		self::assertSame( 0, $this->tenant_count( 'delegations' ) );
		wp_set_current_user( $s['parent'] );
		self::assertSame( 202, $this->json( 'POST', $claim, array( 'subject_id' => $subject ) )->get_status() );
		self::assertSame( 1, $this->tenant_count( 'relationships' ), 'Rejected relationship cannot be reactivated by replay.' );
		self::assertSame( 0, $this->tenant_count( 'delegations' ) );
	}
}
