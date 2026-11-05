<?php
/**
 * Initial 28-table schema and default organization.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use RuntimeException;
use UOP\Core\Clock;
use UOP\Core\PublicId;
/** Each DDL step can be resumed independently; only seed DML uses rollback. */
final class InitialMigration implements Migration {
	/**
	 * Supply the frozen manifest and site context.
	 *
	 * @param Connection      $db Database.
	 * @param MigrationState  $state State.
	 * @param SchemaManifest  $manifest Schema.
	 * @param SchemaInspector $inspector Physical verifier.
	 * @param Clock           $clock UTC clock.
	 * @param string          $prefix Site prefix.
	 * @param string          $charset_collate WordPress collation clause.
	 */
	public function __construct(
		private Connection $db,
		private MigrationState $state,
		private SchemaManifest $manifest,
		private SchemaInspector $inspector,
		private Clock $clock,
		private string $prefix,
		private string $charset_collate
	) {}

	/**
	 * Get initial version.
	 *
	 * @return int
	 */
	public function version(): int {
		return 1;
	}

	/**
	 * Apply one table at a time and then the transactional seed.
	 *
	 * @param MigrationContext $context Checkpoint runner.
	 */
	public function up( MigrationContext $context ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( array_keys( $this->manifest->tables() ) as $name ) {
			$context->step(
				'table_' . $name,
				function () use ( $name ): void {
					// dbDelta is limited to additive initial DDL; verification, not its return messages, determines success.
					$this->db->apply_schema( $this->manifest->ddl( $name, $this->prefix, $this->charset_collate ), Installer::lock_name() );
				},
				fn () => $this->inspector->verify_table( $name )
			);
		}
		$context->step(
			'default_organization',
			function (): void {
				$rows = $this->db->rows( 'SELECT id FROM %i WHERE slug = %s', array( $this->prefix . 'organizations', 'default' ) );
				if ( ! $rows ) {
					$time = $this->clock->now()->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
					$this->db->execute(
						'INSERT INTO %i (public_id, name, slug, status, created_at, updated_at) VALUES (%s, %s, %s, %s, %s, %s)',
						array( $this->prefix . 'organizations', PublicId::generate()->to_binary(), 'Default Organization', 'default', 'active', $time, $time )
					);
					$rows = $this->db->rows( 'SELECT id FROM %i WHERE slug = %s', array( $this->prefix . 'organizations', 'default' ) );
				}
				$this->state->write( 'uop_default_organization_id', (int) $rows[0]['id'] );
			},
			fn () => $this->verify_seed(),
			true
		);
	}

	/**
	 * Verify all schema and seed invariants before version promotion.
	 *
	 * @param MigrationContext $context Execution context.
	 */
	public function verify( MigrationContext $context ): void {
		$this->inspector->verify_all();
		$this->verify_seed();
	}

	/**
	 * Verify the default organization pointer.
	 *
	 * @throws RuntimeException For missing or inconsistent seed.
	 */
	private function verify_seed(): void {
		$rows = $this->db->rows( 'SELECT id, public_id FROM %i WHERE slug = %s', array( $this->prefix . 'organizations', 'default' ) );
		if ( 1 !== count( $rows ) || (int) $rows[0]['id'] !== (int) $this->state->read( 'uop_default_organization_id', 0 ) ) {
			throw new RuntimeException( 'Default organization seed is inconsistent.' );
		}
		PublicId::from_binary( (string) $rows[0]['public_id'] );
	}
}
