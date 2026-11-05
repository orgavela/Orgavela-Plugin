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
		$this->require_manager( $actor, $scope, 'form.manage' );
		$result = array();
		foreach ( $this->forms->page( $scope, new PageRequest( 100 ) ) as $row ) {
			if ( ! $this->policy->can( $actor, 'form.manage', new PolicyObject( $scope->id, 'form', (int) $row['id'] ) )->allowed ) {
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
		return $result;
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
		if ( ! $row || ! $this->policy->can( $actor, 'form.manage', new PolicyObject( $scope->id, 'form', (int) $row['id'] ) )->allowed ) {
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
		$result = array();
		foreach ( $this->events->for_organization( $scope ) as $row ) {
			$event_id = (int) $row['event_post_id'];
			if ( ! $this->policy->can( $actor, 'event.manage', new PolicyObject( $scope->id, 'event', $event_id, null, $event_id ) )->allowed ) {
				continue;
			}
			$result[] = array(
				'public_id'  => PublicId::from_binary( $row['public_id'] )->to_string(),
				'title'      => get_the_title( $event_id ),
				'status'     => (string) $row['status'],
				'visibility' => (string) $row['visibility'],
				'timezone'   => (string) $row['timezone'],
			);
		}
		return $result;
	}

	/**
	 * Read operational event and its manual occurrences via owner scope.
	 *
	 * @param Actor    $actor Current editor.
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $uuid  Event public ID.
	 * @return array<string, mixed>|null
	 */
	public function event( Actor $actor, OrgScope $scope, PublicId $uuid ): ?array {
		$row = $this->events->by_public( $scope, $uuid );
		if ( ! $row ) {
			return null;
		}
		$event_id = (int) $row['event_post_id'];
		if ( ! $this->policy->can( $actor, 'event.manage', new PolicyObject( $scope->id, 'event', $event_id, null, $event_id ) )->allowed ) {
			return null;
		}
		$occurrences = array();
		foreach ( $this->occurrences->for_event( $scope, $event_id ) as $occurrence ) {
			$occurrences[] = array(
				'public_id'     => PublicId::from_binary( $occurrence['public_id'] )->to_string(),
				'starts_at_utc' => (string) $occurrence['start_at'],
				'ends_at_utc'   => (string) $occurrence['end_at'],
				'timezone'      => (string) $occurrence['timezone'],
				'status'        => (string) $occurrence['status'],
			);
		}
		return array(
			'public_id'   => $uuid->to_string(),
			'title'       => get_the_title( $event_id ),
			'status'      => (string) $row['status'],
			'visibility'  => (string) $row['visibility'],
			'timezone'    => (string) $row['timezone'],
			'occurrences' => $occurrences,
		);
	}

	/**
	 * Return policy-filtered profile values, not direct repository rows.
	 *
	 * @param Actor    $actor Active self, delegate or manager.
	 * @param OrgScope $scope Trusted organization.
	 * @param PublicId $uuid  Person public ID.
	 * @return array<string, mixed>|null Null hides inaccessible people.
	 */
	public function profile( Actor $actor, OrgScope $scope, PublicId $uuid ): ?array {
		$person = $this->people->find( $scope, $uuid );
		if ( ! $person ) {
			return null;
		}
		$object = new PolicyObject( $scope->id, 'person', (int) $person['id'] );
		if ( ! $this->policy->can( $actor, 'person.view', $object )->allowed ) {
			return null;
		}
		$rows    = $this->values->for_person( $scope, (int) $person['id'] );
		$grouped = array();
		foreach ( $rows as $row ) {
			$id               = (int) $row['field_id'];
			$grouped[ $id ][] = $row;
		}
		$definitions = array();
		$values      = array();
		foreach ( $this->fields->page( $scope, new PageRequest( 100 ) ) as $field ) {
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
			$items               = $grouped[ (int) $field['id'] ] ?? array();
			$values[ $key ]      = 'multiselect' === $field['data_type']
				? array_map( static fn ( array $item ): mixed => $item['value_string'], $items )
				: ( $items ? self::decode_value( $items[0] ) : null );
		}
		return $this->projection->project( $actor, 'person.view', $object, $uuid, $definitions, $values );
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
