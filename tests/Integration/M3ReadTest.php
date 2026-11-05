<?php
namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use UOP\Application\Policy\{Actor, PolicyObject, PolicyService, ProjectionService};
use UOP\Application\Query\M3ReadService;
use UOP\Application\Form\FormService;
use UOP\Application\Profile\ProfileService;
use UOP\Core\{CorrelationId, PublicId, TransactionManager};
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\{AssignmentRepository, AuditWriter, DelegationRepository, EventRepository, FormRepository, Installer, OccurrenceRepository, OutboxRepository, PersonRepository, ProfileFieldRepository, ProfileValueRepository, SchemaManifest, WpdbConnection};
use UOP\REST\M3Controller;

final class M3ReadTest extends TestCase {
    private WpdbConnection $db;
    private OrgScope $scope;
    private string $prefix;

    protected function setUp(): void {
        global $wpdb;
        $this->db = new WpdbConnection($wpdb);
        $this->prefix = $wpdb->prefix . 'uop_';
        $manifest = new SchemaManifest(dirname(__DIR__, 2) . '/schema/manifest.json');
        foreach (array_keys($manifest->tables()) as $table) $this->db->execute('DROP TABLE IF EXISTS %i', [$this->prefix . $table]);
        foreach (['uop_db_version','uop_data_version','uop_migration_progress_1','uop_migration_status','uop_default_organization_id'] as $key) delete_option($key);
        Installer::runner()->run();
        $this->scope = new OrgScope((int)get_option('uop_default_organization_id'));
    }

    public function test_rest_and_profile_projection_prevent_internal_ids_and_field_leaks(): void {
        $people=new PersonRepository($this->db,$this->prefix);
        $fields=new ProfileFieldRepository($this->db,$this->prefix);
        $values=new ProfileValueRepository($this->db,$this->prefix);
        $forms=new FormRepository($this->db,$this->prefix);
        $manager=wp_create_user('uop_m3_manager_' . bin2hex(random_bytes(4)),wp_generate_password(20),'manager_'.bin2hex(random_bytes(4)).'@example.invalid');
        $member=wp_create_user('uop_m3_member_' . bin2hex(random_bytes(4)),wp_generate_password(20),'member_'.bin2hex(random_bytes(4)).'@example.invalid');
        self::assertIsInt($manager);
        self::assertIsInt($member);
        (new \WP_User($manager))->set_role('administrator');
        $policy=new PolicyService($people,new DelegationRepository($this->db,$this->prefix),new AssignmentRepository($this->db,$this->prefix),static fn(int $id,string $cap): bool => $id === $manager);
        $projector=new ProjectionService($policy);
        $tx=new TransactionManager($this->db,static function(int $n): void {},static function(\Throwable $e): void {});
        $audit=new AuditWriter($this->db,$this->prefix);
        $outbox=new OutboxRepository($this->db,$this->prefix);
        $profile=new ProfileService($fields,$values,$people,$policy,$tx,$audit,$outbox);
        $form=new FormService($forms,$policy,$tx,$audit,$outbox);
        $read=new M3ReadService($people,$fields,$values,new EventRepository($this->db,$this->prefix),new OccurrenceRepository($this->db,$this->prefix),$forms,$policy,$projector);
        $now='2030-01-01 00:00:00';
        $subject=PublicId::generate();
        $people->create($this->scope,$subject,'Subject',null,$now);
        self::assertTrue($people->link($this->scope,$subject,$member,$now));
        $visible=$profile->define(new Actor($manager),$this->scope,['key'=>'display','type'=>'text','label'=>'Display','subject_view'=>true,'sensitivity'=>'personal'],$now,CorrelationId::generate());
        $hidden=$profile->define(new Actor($manager),$this->scope,['key'=>'private_note','type'=>'text','label'=>'Secret','subject_view'=>false,'sensitivity'=>'medical'],$now,CorrelationId::generate());
        $profile->replace_value(new Actor($manager),$this->scope,$subject,$visible,'Hello',$now,CorrelationId::generate());
        $profile->replace_value(new Actor($manager),$this->scope,$subject,$hidden,'medical secret',$now,CorrelationId::generate());
        self::assertSame(['display'=>'Hello'],$read->profile(new Actor($member),$this->scope,$subject)['fields']);
        self::assertSame('medical secret',$read->profile(new Actor($manager),$this->scope,$subject)['fields']['private_note']);
        self::assertNull($read->profile(new Actor($member),new OrgScope($this->scope->id+999),$subject));
        self::assertNull($read->profile(new Actor(999999),$this->scope,$subject));

        $controller=new M3Controller($read,$form,$policy);
        $controller->register();
        wp_set_current_user(0);
        $denied=rest_do_request(new \WP_REST_Request('GET','/uop/v1/forms'));
        self::assertSame(401,$denied->get_status());
        wp_set_current_user($manager);
        $created=$form->create(new Actor($manager),$this->scope,'rest_form','REST form','event',['schema_version'=>1,'fields'=>[['key'=>'name','type'=>'text','label'=>'Name','required'=>true]]],$now,CorrelationId::generate());
        $list=rest_do_request(new \WP_REST_Request('GET','/uop/v1/forms'));
        self::assertSame(200,$list->get_status());
        self::assertSame($created->to_string(),$list->get_data()['items'][0]['public_id']);
        self::assertArrayNotHasKey('id',$list->get_data()['items'][0]);
        $request=new \WP_REST_Request('POST','/uop/v1/forms');
        $request->set_header('content-type','application/json');
        $request->set_body(wp_json_encode([
            'key'=>'injected','title'=>'Injection','context'=>'event',
            'draft'=>['schema_version'=>1,'fields'=>[['key'=>'name','type'=>'text','label'=>'Name','required'=>true]]],
            'status'=>'published',
        ]));
        $invalid=rest_do_request($request);
        self::assertSame(400,$invalid->get_status());
        $profileReq=new \WP_REST_Request('GET','/uop/v1/people/'.$subject->to_string().'/profile');
        $result=rest_do_request($profileReq);
        self::assertSame(200,$result->get_status());
        self::assertSame('medical secret',$result->get_data()['fields']['private_note']);
        wp_set_current_user($member);
        $memberResult=rest_do_request($profileReq);
        self::assertSame(['display'=>'Hello'],$memberResult->get_data()['fields']);

        // A self-service member may read their own projected profile, but must
        // never be able to read a manager form draft or mutate/publish it.
        $formPath = '/uop/v1/forms/' . $created->to_string();
        $formRead = rest_do_request(new \WP_REST_Request('GET', $formPath));
        self::assertSame(403, $formRead->get_status());

        $draftWrite = new \WP_REST_Request('PUT', $formPath . '/draft');
        $draftWrite->set_header('content-type', 'application/json');
        $draftWrite->set_body(wp_json_encode([
            'revision' => 1,
            'draft' => [
                'schema_version' => 1,
                'fields' => [['key' => 'name', 'type' => 'text', 'label' => 'Escalation', 'required' => true]],
            ],
        ]));
        $deniedWrite = rest_do_request($draftWrite);
        self::assertSame(403, $deniedWrite->get_status(), 'Member must not update form drafts.');

        $publishRequest = new \WP_REST_Request('POST', $formPath . '/publish');
        $publishRequest->set_header('content-type', 'application/json');
        $publishRequest->set_body(wp_json_encode(['revision' => 1]));
        $deniedPublish = rest_do_request($publishRequest);
        self::assertSame(403, $deniedPublish->get_status(), 'Member must not publish forms.');

        // Archived records are not live M3 resources, even for managers.
        // Keep the policy boundary fail-closed on reads as well as writes.
        wp_set_current_user($manager);
        $this->db->execute(
            'UPDATE %i SET archived_at = %s WHERE organization_id = %d AND public_id = %s',
            [$this->prefix . 'forms', '2030-01-02 00:00:00', $this->scope->id, $created->to_binary()]
        );
        self::assertNull($read->form(new Actor($manager), $this->scope, $created));
        self::assertSame([], $read->forms(new Actor($manager), $this->scope));
        $archivedForm = rest_do_request(new \WP_REST_Request('GET', $formPath));
        self::assertSame(403, $archivedForm->get_status(), 'Archived form drafts must stay inaccessible.');

        $this->db->execute(
            'UPDATE %i SET status = %s, archived_at = %s WHERE organization_id = %d AND public_id = %s',
            [$this->prefix . 'persons', 'archived', '2030-01-02 00:00:00', $this->scope->id, $subject->to_binary()]
        );
        self::assertNull($read->profile(new Actor($manager), $this->scope, $subject));
        $archivedProfile = rest_do_request($profileReq);
        self::assertSame(403, $archivedProfile->get_status(), 'Archived profile data must stay inaccessible.');

    }

    public function test_form_list_cursor_loads_past_100_and_rejects_invalid_scope(): void {
        $people = new PersonRepository($this->db, $this->prefix);
        $forms = new FormRepository($this->db, $this->prefix);
        $fields = new ProfileFieldRepository($this->db, $this->prefix);
        $manager = wp_create_user('uop_page_' . bin2hex(random_bytes(4)), wp_generate_password(25), 'page_' . bin2hex(random_bytes(4)) . '@example.invalid');
        self::assertIsInt($manager);
        (new \WP_User($manager))->set_role('administrator');
        $actor = new Actor($manager);
        $policy = new PolicyService($people, new DelegationRepository($this->db, $this->prefix), new AssignmentRepository($this->db, $this->prefix), static fn(int $id, string $cap): bool => $id === $manager);
        $tx = new TransactionManager($this->db, static function(int $n): void {}, static function(\Throwable $e): void {});
        $audit = new AuditWriter($this->db, $this->prefix);
        $outbox = new OutboxRepository($this->db, $this->prefix);
        $read = new M3ReadService($people, $fields, new ProfileValueRepository($this->db, $this->prefix), new EventRepository($this->db, $this->prefix), new OccurrenceRepository($this->db, $this->prefix), $forms, $policy, new ProjectionService($policy));
        $form = new FormService($forms, $policy, $tx, $audit, $outbox);
        $controller = new M3Controller($read, $form, $policy);
        $controller->register();
        wp_set_current_user($manager);
        $draft = ['schema_version' => 1, 'fields' => [['key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true]]];
        for ($i = 0; $i < 105; ++$i) {
            $forms->create($this->scope, PublicId::generate(), $manager, 'form_' . $i, 'Synthetic form ' . $i, 'event', $draft, '2030-01-01 00:00:00');
        }
        $first = rest_do_request(new \WP_REST_Request('GET', '/uop/v1/forms'));
        self::assertSame(200, $first->get_status());
        self::assertCount(100, $first->get_data()['items']);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $first->get_data()['next_cursor']);
        self::assertArrayNotHasKey('id', $first->get_data()['items'][0]);

        $secondRequest = new \WP_REST_Request('GET', '/uop/v1/forms');
        $secondRequest->set_param('after', $first->get_data()['next_cursor']);
        $second = rest_do_request($secondRequest);
        self::assertSame(200, $second->get_status());
        self::assertCount(5, $second->get_data()['items']);
        self::assertNull($second->get_data()['next_cursor']);
        self::assertCount(105, array_unique(array_merge(array_column($first->get_data()['items'], 'public_id'), array_column($second->get_data()['items'], 'public_id'))));

        $foreignRequest = new \WP_REST_Request('GET', '/uop/v1/forms');
        $foreignRequest->set_param('after', PublicId::generate()->to_string());
        self::assertSame(400, rest_do_request($foreignRequest)->get_status());
        $invalidRequest = new \WP_REST_Request('GET', '/uop/v1/forms');
        $invalidRequest->set_param('after', '1 OR 1=1');
        self::assertSame(400, rest_do_request($invalidRequest)->get_status());
    }


    public function test_profile_projection_loads_more_than_100_fields_and_1000_values(): void {
        $people = new PersonRepository($this->db, $this->prefix);
        $fields = new ProfileFieldRepository($this->db, $this->prefix);
        $values = new ProfileValueRepository($this->db, $this->prefix);
        $manager = wp_create_user('uop_big_profile_' . bin2hex(random_bytes(4)), wp_generate_password(24), 'big_profile_' . bin2hex(random_bytes(4)) . '@example.invalid');
        self::assertIsInt($manager);
        (new \WP_User($manager))->set_role('administrator');
        $person = PublicId::generate();
        $now = '2030-01-01 00:00:00';
        $people->create($this->scope, $person, 'Synthetic profile', null, $now);
        $personRow = $people->find($this->scope, $person);
        $options = ['choice0','choice1','choice2','choice3','choice4','choice5','choice6','choice7','choice8','choice9'];
        for ($i = 0; $i < 101; ++$i) {
            $fieldId = PublicId::generate();
            $fields->create($this->scope, $fieldId, [
                'key' => 'bulk_' . $i, 'type' => 'multiselect', 'label' => 'Synthetic ' . $i,
                'subject_view' => true, 'sensitivity' => 'personal', 'options' => $options,
            ], $now);
            $fieldRow = $fields->find($this->scope, $fieldId);
            self::assertNotNull($fieldRow);
            $items = [];
            foreach ($options as $ordinal => $choice) {
                $items[] = ['slot' => 'value_string', 'value' => $choice, 'ordinal' => $ordinal];
            }
            if (100 === $i) {
                $items = array_reverse($items);
            }
            $values->replace($this->scope, (int) $personRow['id'], (int) $fieldRow['id'], $items, $now);
        }
        $policy = new PolicyService($people, new DelegationRepository($this->db, $this->prefix), new AssignmentRepository($this->db, $this->prefix), static fn(int $id, string $cap): bool => $id === $manager);
        $reads = new M3ReadService($people, $fields, $values, new EventRepository($this->db, $this->prefix), new OccurrenceRepository($this->db, $this->prefix), new FormRepository($this->db, $this->prefix), $policy, new ProjectionService($policy));
        $profile = $reads->profile(new Actor($manager), $this->scope, $person);
        self::assertNotNull($profile);
        self::assertCount(101, $profile['fields']);
        self::assertSame($options, $profile['fields']['bulk_100'], 'All values remain ordered by ordinal across keyset boundaries.');
        self::assertNull($reads->profile(new Actor($manager), new OrgScope($this->scope->id + 999), $person));
    }


    public function test_event_list_pages_past_100_without_revealing_wordpress_posts(): void {
        $people = new PersonRepository($this->db, $this->prefix);
        $fields = new ProfileFieldRepository($this->db, $this->prefix);
        $values = new ProfileValueRepository($this->db, $this->prefix);
        $events = new EventRepository($this->db, $this->prefix);
        $forms = new FormRepository($this->db, $this->prefix);
        $manager = wp_create_user('uop_event_page_' . bin2hex(random_bytes(4)), wp_generate_password(24), 'event_page_' . bin2hex(random_bytes(4)) . '@example.invalid');
        $reader = wp_create_user('uop_event_reader_' . bin2hex(random_bytes(4)), wp_generate_password(24), 'event_reader_' . bin2hex(random_bytes(4)) . '@example.invalid');
        self::assertIsInt($manager);
        self::assertIsInt($reader);
        (new \WP_User($manager))->set_role('administrator');
        (new \WP_User($reader))->set_role('subscriber');
        if (!post_type_exists('uop_event')) {
            register_post_type('uop_event', ['public' => true]);
        }
        $now = '2030-01-01 00:00:00';
        for ($i = 0; $i < 105; ++$i) {
            $postId = wp_insert_post(['post_type' => 'uop_event', 'post_title' => 'Synthetic event ' . $i, 'post_status' => 'draft', 'post_author' => $manager]);
            self::assertIsInt($postId);
            self::assertGreaterThan(0, $postId);
            $events->create($this->scope, PublicId::generate(), $postId, 'Europe/Berlin', $now);
        }
        // Deliberately over-permissive UOP test resolver: WordPress must
        // still block a subscriber from reading editorial event posts.
        $policy = new PolicyService($people, new DelegationRepository($this->db, $this->prefix), new AssignmentRepository($this->db, $this->prefix), static fn(int $id, string $cap): bool => $id === $manager || $id === $reader);
        $read = new M3ReadService($people, $fields, $values, $events, new OccurrenceRepository($this->db, $this->prefix), $forms, $policy, new ProjectionService($policy));
        $tx = new TransactionManager($this->db, static function(int $n): void {}, static function(\Throwable $e): void {});
        $form = new FormService($forms, $policy, $tx, new AuditWriter($this->db, $this->prefix), new OutboxRepository($this->db, $this->prefix));
        (new M3Controller($read, $form, $policy))->register();
        wp_set_current_user($manager);
        $first = rest_do_request(new \WP_REST_Request('GET', '/uop/v1/events'));
        self::assertSame(200, $first->get_status());
        self::assertCount(100, $first->get_data()['items']);
        self::assertArrayNotHasKey('event_post_id', $first->get_data()['items'][0]);
        self::assertSame($first->get_data()['items'][99]['public_id'], $first->get_data()['next_cursor']);
        $next = new \WP_REST_Request('GET', '/uop/v1/events');
        $next->set_param('after', $first->get_data()['next_cursor']);
        $second = rest_do_request($next);
        self::assertSame(200, $second->get_status());
        self::assertCount(5, $second->get_data()['items']);
        self::assertNull($second->get_data()['next_cursor']);
        $allIds = array_merge(array_column($first->get_data()['items'], 'public_id'), array_column($second->get_data()['items'], 'public_id'));
        self::assertCount(105, array_unique($allIds));

        $forged = new \WP_REST_Request('GET', '/uop/v1/events');
        $forged->set_param('after', PublicId::generate()->to_string());
        self::assertSame(400, rest_do_request($forged)->get_status());
        wp_set_current_user($reader);
        $restricted = rest_do_request(new \WP_REST_Request('GET', '/uop/v1/events'));
        self::assertSame(403, $restricted->get_status(), 'Subscriber route must fail closed.');
        self::assertSame([], $read->events(new Actor($reader), $this->scope), 'UOP manager policy must not bypass the WordPress post gate.');
    }


    public function test_event_occurrences_page_beyond_100_with_stable_timestamp_and_scoped_cursor(): void {
        $people = new PersonRepository($this->db, $this->prefix);
        $fields = new ProfileFieldRepository($this->db, $this->prefix);
        $values = new ProfileValueRepository($this->db, $this->prefix);
        $events = new EventRepository($this->db, $this->prefix);
        $occurrences = new OccurrenceRepository($this->db, $this->prefix);
        $forms = new FormRepository($this->db, $this->prefix);
        $manager = wp_create_user('uop_occ_page_' . bin2hex(random_bytes(4)), wp_generate_password(24), 'occ_page_' . bin2hex(random_bytes(4)) . '@example.invalid');
        $stranger = wp_create_user('uop_occ_denied_' . bin2hex(random_bytes(4)), wp_generate_password(24), 'occ_denied_' . bin2hex(random_bytes(4)) . '@example.invalid');
        self::assertIsInt($manager);
        self::assertIsInt($stranger);
        (new \WP_User($manager))->set_role('administrator');
        (new \WP_User($stranger))->set_role('subscriber');
        if (!post_type_exists('uop_event')) {
            register_post_type('uop_event', ['public' => true]);
        }
        $eventPostId = wp_insert_post(['post_type' => 'uop_event', 'post_title' => 'Fixture event', 'post_status' => 'draft', 'post_author' => $manager]);
        $otherPostId = wp_insert_post(['post_type' => 'uop_event', 'post_title' => 'Other event', 'post_status' => 'draft', 'post_author' => $manager]);
        self::assertIsInt($eventPostId);
        self::assertIsInt($otherPostId);
        $eventId = PublicId::generate();
        $otherEventId = PublicId::generate();
        $now = '2030-01-01 00:00:00';
        $events->create($this->scope, $eventId, $eventPostId, 'Europe/Berlin', $now);
        $events->create($this->scope, $otherEventId, $otherPostId, 'Europe/Berlin', $now);
        $window = new \UOP\Domain\Events\OccurrenceWindow('2030-01-20T10:00:00+01:00', '2030-01-20T11:00:00+01:00', 'Europe/Berlin');
        for ($i = 0; $i < 105; ++$i) {
            // Identical UTC start tests the composite (start_at,id) cursor.
            $occurrences->create($this->scope, PublicId::generate(), $eventPostId, $window, $now);
        }
        $foreignCursor = PublicId::generate();
        $occurrences->create($this->scope, $foreignCursor, $otherPostId, $window, $now);
        $policy = new PolicyService($people, new DelegationRepository($this->db, $this->prefix), new AssignmentRepository($this->db, $this->prefix), static fn(int $id, string $cap): bool => $id === $manager || $id === $stranger);
        $reads = new M3ReadService($people, $fields, $values, $events, $occurrences, $forms, $policy, new ProjectionService($policy));
        $tx = new TransactionManager($this->db, static function(int $n): void {}, static function(\Throwable $e): void {});
        $formsService = new FormService($forms, $policy, $tx, new AuditWriter($this->db, $this->prefix), new OutboxRepository($this->db, $this->prefix));
        (new M3Controller($reads, $formsService, $policy))->register();

        $path = '/uop/v1/events/' . $eventId->to_string();
        wp_set_current_user($manager);
        $first = rest_do_request(new \WP_REST_Request('GET', $path));
        self::assertSame(200, $first->get_status());
        self::assertCount(100, $first->get_data()['occurrences']);
        self::assertArrayNotHasKey('id', $first->get_data()['occurrences'][0]);
        self::assertArrayNotHasKey('event_post_id', $first->get_data()['occurrences'][0]);
        self::assertSame($first->get_data()['occurrences'][99]['public_id'], $first->get_data()['occurrences_next_cursor']);
        $continuation = new \WP_REST_Request('GET', $path);
        $continuation->set_param('after_occurrence', $first->get_data()['occurrences_next_cursor']);
        $second = rest_do_request($continuation);
        self::assertSame(200, $second->get_status());
        self::assertCount(5, $second->get_data()['occurrences']);
        self::assertNull($second->get_data()['occurrences_next_cursor']);
        $ids = array_merge(array_column($first->get_data()['occurrences'], 'public_id'), array_column($second->get_data()['occurrences'], 'public_id'));
        self::assertCount(105, array_unique($ids));

        $wrongEvent = new \WP_REST_Request('GET', $path);
        $wrongEvent->set_param('after_occurrence', $foreignCursor->to_string());
        self::assertSame(400, rest_do_request($wrongEvent)->get_status());
        $invalid = new \WP_REST_Request('GET', $path);
        $invalid->set_param('after_occurrence', 'invalid SQL cursor');
        self::assertSame(400, rest_do_request($invalid)->get_status());
        self::assertNull($reads->event(new Actor($manager), new OrgScope($this->scope->id + 200), $eventId));
        wp_set_current_user($stranger);
        $denied = rest_do_request(new \WP_REST_Request('GET', $path));
        self::assertSame(403, $denied->get_status());
    }
}
