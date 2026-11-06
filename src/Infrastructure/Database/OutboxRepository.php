<?php
/**
 * Transactional outbox persistence.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use InvalidArgumentException;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/** No messages leave the database before their business transaction commits. */
final class OutboxRepository {
	/**
	 * Bind the organization-owned event table.
	 *
	 * @param Connection $db     Live database connection.
	 * @param string     $prefix Trusted table prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Insert an immutable, minimally described event alongside the domain change.
	 *
	 * @param OrgScope              $scope       Trusted organization boundary.
	 * @param PublicId              $uuid        Event idempotency identity.
	 * @param string                $aggregate   Whitelisted aggregate name.
	 * @param int                   $aggregate_id Internal aggregate primary key.
	 * @param string                $event_name  Stable domain event name.
	 * @param CorrelationId         $correlation Command correlation identifier.
	 * @param array<string, string> $payload     Allowlisted non-sensitive metadata.
	 * @throws InvalidArgumentException For out-of-contract metadata.
	 */
	public function append( OrgScope $scope, PublicId $uuid, string $aggregate, int $aggregate_id, string $event_name, CorrelationId $correlation, array $payload ): void {
		if ( $aggregate_id < 1 || ! in_array( $aggregate, array( 'person', 'delegation', 'assignment', 'registration', 'capacity', 'consent', 'event', 'form', 'profile_field' ), true ) || ! preg_match( '/^[a-z]+(?:\.[a-z_]+)+$/D', $event_name ) ) {
			throw new InvalidArgumentException( 'Invalid domain event.' );
		}
		foreach ( $payload as $key => $value ) {
			if ( ! in_array( $key, array( 'public_id', 'subject_public_id', 'command_id', 'status', 'reason_code', 'previous_status', 'new_status' ), true ) || strlen( $value ) > 100 || ! preg_match( '/^[a-zA-Z0-9_.:-]*$/D', $value ) ) {
				throw new InvalidArgumentException( 'Unsafe domain event metadata.' );
			}
		}
		$this->db->execute(
			'INSERT INTO %i (event_uuid, organization_id, aggregate_type, aggregate_id, event_name, correlation_id, payload_json, occurred_at) VALUES (%s, %d, %s, %d, %s, %s, %s, UTC_TIMESTAMP())',
			array(
				$this->prefix . 'domain_events',
				$uuid->to_binary(),
				$scope->id,
				$aggregate,
				$aggregate_id,
				$event_name,
				$correlation->to_binary(),
				(string) wp_json_encode( $payload, JSON_THROW_ON_ERROR ),
			)
		);
	}

	/**
	 * Fetch a bounded batch of undispatched events for one organization.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @return list<array<string, mixed>>
	 */
	public function pending( OrgScope $scope ): array {
		return $this->db->rows(
			'SELECT id, event_uuid, aggregate_type, aggregate_id, event_name, payload_json, correlation_id, attempts FROM %i WHERE organization_id = %d AND published_at IS NULL AND attempts < 5 ORDER BY id ASC LIMIT 50',
			array( $this->prefix . 'domain_events', $scope->id )
		);
	}

	/**
	 * Find one event for its queued idempotent worker.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $uuid  Event public identifier.
	 * @return array<string, mixed>|null
	 */
	public function find_pending( OrgScope $scope, PublicId $uuid ): ?array {
		$rows = $this->db->rows(
			'SELECT id, event_uuid, aggregate_type, aggregate_id, event_name, payload_json, correlation_id, attempts FROM %i WHERE organization_id = %d AND event_uuid = %s AND published_at IS NULL AND attempts < 5 LIMIT 1',
			array( $this->prefix . 'domain_events', $scope->id, $uuid->to_binary() )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Complete one dispatched event without overwriting another organization's status.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $uuid  Event public identifier.
	 */
	public function mark_published( OrgScope $scope, PublicId $uuid ): void {
		$this->db->execute(
			'UPDATE %i SET published_at = UTC_TIMESTAMP(), last_error = NULL WHERE organization_id = %d AND event_uuid = %s AND published_at IS NULL',
			array( $this->prefix . 'domain_events', $scope->id, $uuid->to_binary() )
		);
	}

	/**
	 * Record safe retry diagnostics without logging event payload contents.
	 *
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $uuid  Event public identifier.
	 * @param string   $code  Stable failure class.
	 */
	public function failed_attempt( OrgScope $scope, PublicId $uuid, string $code ): void {
		$this->db->execute(
			'UPDATE %i SET attempts = attempts + 1, last_error = %s WHERE organization_id = %d AND event_uuid = %s AND published_at IS NULL',
			array( $this->prefix . 'domain_events', substr( preg_replace( '/[^a-zA-Z0-9_]/', '_', $code ), 0, 100 ), $scope->id, $uuid->to_binary() )
		);
	}
}
