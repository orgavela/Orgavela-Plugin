<?php
/**
 * Serialized, tenant-scoped guardian claim attempts in existing WordPress options.
 *
 * @package UOP
 */

namespace UOP\Infrastructure\Database;

use InvalidArgumentException;
use UOP\Domain\Organization\OrgScope;

/**
 * Quota decisions belong to the same InnoDB transaction as the claim.
 * Never read WordPress transient/object caches for admission decisions.
 */
final class GuardianClaimQuotaRepository {
	private const LIMIT          = 3;
	private const WINDOW_SECONDS = 86400;

	/**
	 * Set up site-local quota storage without introducing new tables.
	 *
	 * @param Connection $db            Shared transactional connection.
	 * @param string     $options_table Trusted site-local InnoDB wp_options table.
	 * @throws InvalidArgumentException For an invalid options table identity.
	 */
	public function __construct( private Connection $db, private string $options_table ) {
		if ( ! preg_match( '/^[A-Za-z0-9_]+options$/D', $options_table ) ) {
			throw new InvalidArgumentException( 'Invalid guardian quota storage.' );
		}
	}

	/**
	 * Atomically consume one claim attempt, whether the subject exists or not.
	 * Call only *inside* the owning TransactionManager::run() transaction.
	 * An existing options row is explicitly locked before inspecting its value;
	 * all concurrent workers serialize on the same option_name unique key.
	 *
	 * @param OrgScope $scope    Server-trusted tenant.
	 * @param int      $actor_id Authenticated, linked WordPress account.
	 * @param int      $now       Server-generated Unix timestamp.
	 * @return bool Whether a quota slot was successfully consumed.
	 * @throws DatabaseException For missing, malformed or failed storage.
	 * @throws InvalidArgumentException For invalid trusted inputs.
	 */
	public function consume( OrgScope $scope, int $actor_id, int $now ): bool {
		if ( $actor_id < 1 || $now < 1 ) {
			throw new InvalidArgumentException( 'Invalid guardian claim quota identity.' );
		}
		// No subject UUID, account name or email in the storage key.
		$key = 'uop_gclaim_q2_' . hash( 'sha256', $scope->id . ':' . $actor_id );
		$this->db->execute(
			"INSERT INTO %i (option_name, option_value, autoload) VALUES (%s,%s,'off') ON DUPLICATE KEY UPDATE option_name = option_name",
			array( $this->options_table, $key, '0:0' )
		);
		$rows = $this->db->rows(
			'SELECT option_value FROM %i WHERE option_name = %s LIMIT 1 FOR UPDATE',
			array( $this->options_table, $key )
		);
		if ( 1 !== count( $rows ) || ! is_string( $rows[0]['option_value'] ?? null )
			|| ! preg_match( '/^([0-9]{1,2}):([0-9]{1,12})$/D', $rows[0]['option_value'], $parts ) ) {
			throw new DatabaseException( 'Guardian claim quota unavailable.' );
		}
		$count   = (int) $parts[1];
		$expires = (int) $parts[2];
		if ( $count > self::LIMIT ) {
			throw new DatabaseException( 'Guardian claim quota unavailable.' );
		}
		if ( $expires > $now && $count >= self::LIMIT ) {
			return false;
		}
		$next_count = $expires > $now ? $count + 1 : 1;
		$next_until = $expires > $now ? $expires : $now + self::WINDOW_SECONDS;
		if ( 1 !== $this->db->execute(
			'UPDATE %i SET option_value = %s WHERE option_name = %s',
			array( $this->options_table, $next_count . ':' . $next_until, $key )
		) ) {
			throw new DatabaseException( 'Guardian claim quota could not be recorded.' );
		}
		return true;
	}
}
