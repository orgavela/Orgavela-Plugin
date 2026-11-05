<?php
/**
 * Minimal, application-append-only audit infrastructure.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use InvalidArgumentException;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\PolicyObject;
use UOP\Core\CorrelationId;
use UOP\Core\PublicId;
use UOP\Domain\Organization\OrgScope;

/** Writes audit evidence without arbitrary private payloads. */
final class AuditWriter {
	/**
	 * Bind the site's audit log.
	 *
	 * @param Connection $db     Live database connection.
	 * @param string     $prefix Trusted WordPress table prefix.
	 */
	public function __construct( private Connection $db, private string $prefix ) {}

	/**
	 * Append one auditable result within the caller's transaction.
	 *
	 * @param OrgScope                  $scope       Trusted organization boundary.
	 * @param Actor                     $actor       WordPress actor, or system actor zero.
	 * @param string                    $action      Stable domain action key.
	 * @param PolicyObject              $domain_object      Scoped object reference.
	 * @param string                    $result      Allowed or rejected outcome.
	 * @param CorrelationId             $correlation Request correlation identifier.
	 * @param PublicId|null             $event       Domain event public ID.
	 * @param array<string, int|string> $metadata Safe, allowlisted diagnostic values.
	 * @throws InvalidArgumentException When metadata contains unsafe content.
	 */
	public function append( OrgScope $scope, Actor $actor, string $action, PolicyObject $domain_object, string $result, CorrelationId $correlation, ?PublicId $event = null, array $metadata = array() ): void {
		if ( $scope->id !== $domain_object->organization_id || ! preg_match( '/^[a-z][a-z0-9_.]{1,99}$/D', $action ) || ! in_array( $result, array( 'allowed', 'denied', 'success', 'failed' ), true ) ) {
			throw new InvalidArgumentException( 'Invalid audit boundary.' );
		}
		foreach ( $metadata as $key => $value ) {
			if ( ! in_array( $key, array( 'reason_code', 'command_id', 'status', 'previous_status', 'new_status' ), true ) || ( is_string( $value ) && ( strlen( $value ) > 100 || ! preg_match( '/^[a-zA-Z0-9_.:-]*$/D', $value ) ) ) ) {
				throw new InvalidArgumentException( 'Unsafe audit metadata.' );
			}
		}
		$this->db->execute(
			'INSERT INTO %i (organization_id, occurred_at, actor_user_id, action, object_type, object_id, subject_person_id, result, correlation_id, event_uuid, data_json) VALUES (%d, UTC_TIMESTAMP(), NULLIF(%d,0), %s, %s, %d, NULLIF(%d,0), %s, %s, NULLIF(%s, %s), %s)',
			array(
				$this->prefix . 'audit_log',
				$scope->id,
				$actor->user_id,
				$action,
				$domain_object->type,
				$domain_object->id,
				$domain_object->subject_person_id ?? 0,
				$result,
				$correlation->to_binary(),
				$event?->to_binary() ?? '',
				'',
				(string) wp_json_encode( $metadata, JSON_THROW_ON_ERROR ),
			)
		);
	}
}
