<?php
/**
 * Policy-projected Profile, Event and Form read models.
 *
 * @package UOP
 */

namespace UOP\Application\Query;

use RuntimeException;
use UOP\Application\Policy\Actor;
use UOP\Application\Policy\FieldDefinition;
use UOP\Application\Policy\PolicyObject;
use UOP\Application\Policy\PolicyService;
use UOP\Application\Policy\ProjectionService;
use UOP\Core\PublicId;
use UOP\Domain\Forms\FormSchema;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\EventRepository;
use UOP\Infrastructure\Database\FormRepository;
use UOP\Infrastructure\Database\OccurrenceRepository;
use UOP\Infrastructure\Database\PageRequest;
use UOP\Infrastructure\Database\PersonRepository;
use UOP\Infrastructure\Database\ProfileFieldRepository;
use UOP\Infrastructure\Database\ProfileValueRepository;

/** Never returns internal DB IDs or raw, unprojected profile values. */
final class M3ReadService {
	/**
	 * Compose existing M2 policy with scoped M3 read repositories.
	 *
	 * @param PersonRepository       $people      Person owner lookup.
	 * @param ProfileFieldRepository $fields      Field policies and metadata.
	 * @param ProfileValueRepository $values      Typed profile values.
	 * @param EventRepository        $events      Event settings.
	 * @param OccurrenceRepository   $occurrences Scoped occurrence reads.
	 * @param FormRepository         $forms       Draft and version pointer.
	 * @param PolicyService          $policy      Central authorization.
	 * @param ProjectionService      $projection Central field projection.
	 */
	public function __construct(
		private PersonRepository $people,
		private ProfileFieldRepository $fields,
		private ProfileValueRepository $values,
		private EventRepository $events,
		private OccurrenceRepository $occurrences,
		private FormRepository $forms,
		private PolicyService $policy,
		private ProjectionService $projection
	) {}

	/**
	 * Return manager-accessible form summaries, with public IDs only.
	 *
	 * @param Actor    $actor Authenticated actor.
	 * @param OrgScope $scope Trusted organization.
	 * @return list<array<string, mixed>>
	 */
	public function forms( Actor $actor, OrgScope $scope ): array {
		return $this->form_page( $actor, $scope, null )['items'];
	}

	/**
	 * One authorized, keyset-bounded form page. The public cursor is a UUID,
	 * never an exposed numeric database key.
	 *
	 * @param Actor         $actor Authenticated manager.
	 * @param OrgScope      $scope Organization boundary.
	 * @param PublicId|null $after Last public form ID from the previous page.
	 * @return array{items:list<array<string,mixed>>,next_cursor:?string}
	 * @throws \InvalidArgumentException For a cursor outside the organization.
	 */
	public function form_page( Actor $actor, OrgScope $scope, ?PublicId $after ): array {
		$this->require_manager( $actor, $scope, 'form.manage' );
		$after_id = 0;
		if ( null !== $after ) {
			$cursor = $this->forms->find( $scope, $after );
			if ( ! $cursor ) {
				throw new \InvalidArgumentException( 'Invalid form page cursor.' );
			}
			$after_id = (int) $cursor['id'];
		}
		$rows   = $this->forms->page( $scope, new PageRequest( 100, $after_id ) );
		$result = array();
		foreach ( $rows as $row ) {
			if ( null !== $row['archived_at'] || ! $this->policy->can( $actor, 'form.manage', new PolicyObject( $scope->id, 'form', (int) $row['id'] ) )->allowed ) {
				continue;
			}
			$result[] = array(
				'public_id' => PublicId::from_binary( $row['public_id'] )->to_string(),
				'key'       => (string) $row['form_key'],
				'title'     => (string) $row['title'],
				'status'    => (string) $row['status'],
				'revision'  => (int) $row['draft_revision'],
			);
		}
		$next_cursor = count( $rows ) === 100 ? PublicId::from_binary( $rows[99]['public_id'] )->to_string() : null;
		return array(
			'items'       => $result,
			'next_cursor' => $next_cursor,
		);
	}

	/**
	 * Load one authorized draft and immutable published-version pointer.
	 *
	 * @param Actor    $actor Current editor.
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $uuid  Public form ID.
	 * @return array<string, mixed>|null
	 */
	public function form( Actor $actor, OrgScope $scope, PublicId $uuid ): ?array {
		$row = $this->forms->find( $scope, $uuid );
		if ( ! $row || null !== $row['archived_at'] || ! $this->policy->can( $actor, 'form.manage', new PolicyObject( $scope->id, 'form', (int) $row['id'] ) )->allowed ) {
			return null;
		}
		$draft = json_decode( (string) $row['draft_schema_json'], true, 64, JSON_THROW_ON_ERROR );
		( new FormSchema() )->validate_draft( $draft );
		$published = $this->forms->current_published( $scope, (int) $row['id'] );
		return array(
			'public_id' => $uuid->to_string(),
			'key'       => (string) $row['form_key'],
			'title'     => (string) $row['title'],
			'context'   => (string) $row['context'],
			'status'    => (string) $row['status'],
			'revision'  => (int) $row['draft_revision'],
			'draft'     => $draft,
			'published' => $published ? array(
				'public_id' => PublicId::from_binary( $published['public_id'] )->to_string(),
				'version'   => (int) $published['version'],
				'sha256'    => bin2hex( $published['checksum'] ),
			) : null,
		);
	}

	/**
	 * List scoped events for a manager, respecting object-level assignment.
	 *
	 * @param Actor    $actor Current editor.
	 * @param OrgScope $scope Trusted organization.
	 * @return list<array<string, mixed>>
	 */
	public function events( Actor $actor, OrgScope $scope ): array {
		return $this->event_page( $actor, $scope, null )['items'];
	}

	/**
	 * Fetch up to 100 policy- and WordPress-authorized events.
	 *
	 * Only a visible event UUID is used as the external cursor; denied
	 * raw database records must never become public pagination tokens.
	 *
	 * @param Actor         $actor Authenticated actor.
	 * @param OrgScope      $scope Trusted organization.
	 * @param PublicId|null $after Authorized public event from previous page.
	 * @return array{items:list<array<string,mixed>>,next_cursor:?string}
	 * @throws \InvalidArgumentException If the cursor does not refer to an accessible event.
	 * @throws RuntimeException If the authorization scan exceeds its safe limit.
	 */
	public function event_page( Actor $actor, OrgScope $scope, ?PublicId $after ): array {
		$after_post_id = 0;
		if ( null !== $after ) {
			$cursor = $this->events->by_public( $scope, $after );
			if ( ! $cursor || ! $this->can_read_event( $actor, $scope, (int) $cursor['event_post_id'] ) ) {
				throw new \InvalidArgumentException( 'Invalid event cursor.' );
			}
			$after_post_id = (int) $cursor['event_post_id'];
		}
		$result = array();
		// Scan bounded raw pages until a full authorized page is assembled;
		// never leak private event IDs merely to advance past hidden rows.
		for ( $batch = 0; $batch < 20; ++$batch ) {
			$rows = $this->events->for_organization( $scope, $after_post_id );
			foreach ( $rows as $row ) {
				$event_id = (int) $row['event_post_id'];
				if ( ! $this->can_read_event( $actor, $scope, $event_id ) ) {
					continue;
				}
				$result[] = array(
					'public_id'  => PublicId::from_binary( $row['public_id'] )->to_string(),
					'title'      => get_the_title( $event_id ),
					'status'     => (string) $row['status'],
					'visibility' => (string) $row['visibility'],
					'timezone'   => (string) $row['timezone'],
				);
				if ( 100 === count( $result ) ) {
					return array(
						'items'       => $result,
						'next_cursor' => $result[99]['public_id'],
					);
				}
			}
			if ( count( $rows ) < 100 ) {
				return array(
					'items'       => $result,
					'next_cursor' => null,
				);
			}
			$after_post_id = (int) $rows[ count( $rows ) - 1 ]['event_post_id'];
		}
		throw new RuntimeException( 'Event listing exceeds the safe authorization scan limit.' );
	}

	/**
	 * Read operational event and its manual occurrences via owner scope.
	 *
	 * @param Actor    $actor Current editor.
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId      $uuid Event public ID.
	 * @param PublicId|null $after_occurrence Public cursor for next occurrence page.
	 * @return array<string, mixed>|null
	 * @throws \InvalidArgumentException If an occurrence cursor belongs to another event.
	 */
	public function event( Actor $actor, OrgScope $scope, PublicId $uuid, ?PublicId $after_occurrence = null ): ?array {
		$row = $this->events->by_public( $scope, $uuid );
		if ( ! $row ) {
			return null;
		}
		$event_id = (int) $row['event_post_id'];
		if ( ! $this->can_read_event( $actor, $scope, $event_id ) ) {
			return null;
		}
		$after_start = null;
		$after_id    = 0;
		if ( null !== $after_occurrence ) {
			$cursor = $this->occurrences->event_cursor( $scope, $event_id, $after_occurrence );
			if ( ! $cursor ) {
				throw new \InvalidArgumentException( 'Occurrence cursor is not owned by the selected event.' );
			}
			$after_start = (string) $cursor['start_at'];
			$after_id    = (int) $cursor['id'];
		}
		$occurrences = array();
		$rows        = $this->occurrences->for_event_page( $scope, $event_id, $after_start, $after_id );
		foreach ( $rows as $occurrence ) {
			$occurrences[] = array(
				'public_id'     => PublicId::from_binary( $occurrence['public_id'] )->to_string(),
				'starts_at_utc' => (string) $occurrence['start_at'],
				'ends_at_utc'   => (string) $occurrence['end_at'],
				'timezone'      => (string) $occurrence['timezone'],
				'status'        => (string) $occurrence['status'],
			);
		}
		$next_cursor = count( $rows ) === 100 ? $occurrences[99]['public_id'] : null;
		return array(
			'public_id'               => $uuid->to_string(),
			'title'                   => get_the_title( $event_id ),
			'status'                  => (string) $row['status'],
			'visibility'              => (string) $row['visibility'],
			'timezone'                => (string) $row['timezone'],
			'occurrences'             => $occurrences,
			'occurrences_next_cursor' => $next_cursor,
		);
	}

	/**
	 * Return policy-filtered profile values, not direct repository rows.
	 *
	 * @param Actor    $actor Active self, delegate or manager.
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $uuid  Person public ID.
	 * @return array<string, mixed>|null Null hides inaccessible people.
	 * @throws RuntimeException When the profile exceeds safe bounded projection limits.
	 */
	public function profile( Actor $actor, OrgScope $scope, PublicId $uuid ): ?array {
		$person = $this->people->find( $scope, $uuid );
		if ( ! $person || 'active' !== $person['status'] || null !== $person['archived_at'] ) {
			return null;
		}
		$object = new PolicyObject( $scope->id, 'person', (int) $person['id'] );
		if ( ! $this->policy->can( $actor, 'person.view', $object )->allowed ) {
			return null;
		}
		$grouped     = array();
		$after_value = 0;
		// Never return a partially projected profile when a fixture is
		// unexpectedly large; bounded keysets avoid a single huge SQL read.
		for ( $batch = 0; $batch <= 100; ++$batch ) {
			$rows = $this->values->for_person_page( $scope, (int) $person['id'], $after_value );
			if ( 100 === $batch && $rows ) {
				throw new RuntimeException( 'Profile exceeds the safe value projection limit.' );
			}
			foreach ( $rows as $row ) {
				$id               = (int) $row['field_id'];
				$grouped[ $id ][] = $row;
			}
			if ( count( $rows ) < 100 ) {
				break;
			}
			$after_value = (int) $rows[ count( $rows ) - 1 ]['id'];
		}
		foreach ( $grouped as &$items ) {
			usort( $items, static fn ( array $a, array $b ): int => (int) $a['ordinal'] <=> (int) $b['ordinal'] );
		}
		unset( $items );

		$definitions = array();
		$values      = array();
		$after_field = 0;
		for ( $batch = 0; $batch <= 100; ++$batch ) {
			$field_rows = $this->fields->page( $scope, new PageRequest( 100, $after_field ) );
			if ( 100 === $batch && $field_rows ) {
				throw new RuntimeException( 'Profile exceeds the safe field projection limit.' );
			}
			foreach ( $field_rows as $field ) {
				if ( 'active' !== $field['status'] ) {
					continue;
				}
				$key                 = (string) $field['field_key'];
				$definitions[ $key ] = new FieldDefinition(
					$key,
					(string) $field['sensitivity'],
					(bool) $field['subject_view'],
					(bool) $field['subject_edit'],
					(bool) $field['delegate_view'],
					(bool) $field['delegate_edit']
				);
				$field_items         = $grouped[ (int) $field['id'] ] ?? array();
				$values[ $key ]      = 'multiselect' === $field['data_type']
					? array_map( static fn ( array $item ): mixed => $item['value_string'], $field_items )
					: ( $field_items ? self::decode_value( $field_items[0] ) : null );
			}
			if ( count( $field_rows ) < 100 ) {
				break;
			}
			$after_field = (int) $field_rows[ count( $field_rows ) - 1 ]['id'];
		}
		return $this->projection->project( $actor, 'person.view', $object, $uuid, $definitions, $values );
	}


	/**
	 * Require both tenant policy and actual WordPress post ownership rights.
	 *
	 * @param Actor    $actor Current actor.
	 * @param OrgScope $scope Trusted organization.
	 * @param int      $event_id Trusted scoped WordPress post ID.
	 * @return bool
	 */
	private function can_read_event( Actor $actor, OrgScope $scope, int $event_id ): bool {
		if ( $event_id < 1 ) {
			return false;
		}
		$post = get_post( $event_id );
		return null !== $post && 'uop_event' === $post->post_type && 'trash' !== $post->post_status
			&& user_can( $actor->user_id, 'edit_post', $event_id )
			&& $this->policy->can( $actor, 'event.manage', new PolicyObject( $scope->id, 'event', $event_id, null, $event_id ) )->allowed;
	}

	/**
	 * Authorize an organizational read through the same M2 object policy.
	 *
	 * @param Actor    $actor Current manager.
	 * @param OrgScope $scope Trusted organization.
	 * @param string   $action Authoritative capability action.
	 * @throws RuntimeException When no organization-wide scope applies.
	 */
	private function require_manager( Actor $actor, OrgScope $scope, string $action ): void {
		if ( ! $this->policy->can( $actor, $action, new PolicyObject( $scope->id, 'organization', $scope->id ) )->allowed ) {
			throw new RuntimeException( 'Organization read denied.' );
		}
	}

	/**
	 * Decode at most one non-null typed value slot.
	 *
	 * @param array<string, mixed> $row Scoped field-value row.
	 * @return mixed
	 */
	private static function decode_value( array $row ): mixed {
		if ( null !== $row['value_boolean'] ) {
			return 1 === (int) $row['value_boolean'];
		}
		foreach ( array( 'value_string', 'value_text', 'value_decimal', 'value_date' ) as $slot ) {
			if ( null !== $row[ $slot ] ) {
				return (string) $row[ $slot ];
			}
		}
		return null;
	}
}
