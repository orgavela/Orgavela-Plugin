<?php
namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use UOP\Application\Event\EventService;
use UOP\Application\Form\FormService;
use UOP\Application\Profile\ProfileService;
use UOP\Application\Policy\{Actor, PolicyService};
use UOP\Core\{CorrelationId, PublicId, TransactionManager};
use UOP\Domain\Events\OccurrenceWindow;
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\{AssignmentRepository, AuditWriter, DelegationRepository, EventRepository, FormRepository, Installer, OccurrenceRepository, OutboxRepository, PersonRepository, ProfileFieldRepository, ProfileValueRepository, SchemaManifest, WpdbConnection};

final class M3PersistenceTest extends TestCase {
    private WpdbConnection $db;
    private OrgScope $scope;
    private string $prefix;

    protected function setUp(): void {
        global $wpdb;
        $this->db = new WpdbConnection($wpdb);
        $this->prefix = $wpdb->prefix . 'uop_';
        $manifest = new SchemaManifest(dirname(__DIR__, 2) . '/schema/manifest.json');
        foreach (array_keys($manifest->tables()) as $table) {
            $this->db->execute('DROP TABLE IF EXISTS %i', [$this->prefix . $table]);
        }
        foreach (['uop_db_version','uop_data_version','uop_migration_progress_1','uop_migration_status','uop_default_organization_id'] as $key) delete_option($key);
        Installer::runner()->run();
        $this->scope = new OrgScope((int)get_option('uop_default_organization_id'));
    }

    private function services(): array {
        $people = new PersonRepository($this->db,$this->prefix);
        $fields = new ProfileFieldRepository($this->db,$this->prefix);
        $values = new ProfileValueRepository($this->db,$this->prefix);
        $forms = new FormRepository($this->db,$this->prefix);
        $events = new EventRepository($this->db,$this->prefix);
        $occurrences = new OccurrenceRepository($this->db,$this->prefix);
        $id = wp_create_user('uop_m3_' . bin2hex(random_bytes(4)),wp_generate_password(32),'m3_'.bin2hex(random_bytes(4)).'@example.invalid');
        self::assertIsInt($id);
        $user = new \WP_User($id);
        $user->set_role('administrator');
        wp_set_current_user($id);
        $actor = new Actor($id);
        $policy = new PolicyService($people,new DelegationRepository($this->db,$this->prefix),new AssignmentRepository($this->db,$this->prefix),static fn(int $user_id,string $cap): bool => $user_id === $id);
        $tx = new TransactionManager($this->db, static function(int $n): void {}, static function(\Throwable $e): void {});
        $audit = new AuditWriter($this->db,$this->prefix);
        $outbox = new OutboxRepository($this->db,$this->prefix);
        return [
            'actor'=>$actor,'people'=>$people,'fields'=>$fields,'forms'=>$forms,'events'=>$events,
            'profile'=>new ProfileService($fields,$values,$people,$policy,$tx,$audit,$outbox),
            'form'=>new FormService($forms,$policy,$tx,$audit,$outbox),
            'event'=>new EventService($events,$occurrences,$policy,$tx,$audit,$outbox),
        ];
    }

    public function test_profile_value_slots_and_cross_org_are_isolated(): void {
        $s=$this->services();
        $now='2030-01-01 10:00:00';
        $person=PublicId::generate();
        $s['people']->create($this->scope,$person,'Example',null,$now);
        $field=$s['profile']->define($s['actor'],$this->scope,[
            'key'=>'interests','type'=>'multiselect','label'=>'Interests','sensitivity'=>'personal','options'=>['music','sport']
        ],$now,CorrelationId::generate());
        $s['profile']->replace_value($s['actor'],$this->scope,$person,$field,['sport','music'],$now,CorrelationId::generate());
        $p=$s['people']->find($this->scope,$person);
        $f=$s['fields']->find($this->scope,$field);
        $rows=$this->db->rows('SELECT ordinal, value_string, value_text, value_boolean FROM %i WHERE person_id = %d AND field_id = %d ORDER BY ordinal ASC',[$this->prefix.'profile_values',(int)$p['id'],(int)$f['id']]);
        self::assertSame([0,1],array_map(static fn(array $r): int => (int)$r['ordinal'],$rows));
        self::assertSame(['sport','music'],array_column($rows,'value_string'));
        self::assertNull($rows[0]['value_text']);
        self::assertFalse($s['fields']->may_change_type($this->scope,$field,'number'));
        self::assertNull($s['fields']->find(new OrgScope($this->scope->id+100),$field));
    }

    public function test_form_revision_immutability_and_consent_pins(): void {
        $s=$this->services();
        $draft=['schema_version'=>1,'fields'=>[['key'=>'name','type'=>'text','label'=>'Name','required'=>true]]];
        $now='2030-01-01 10:00:00';
        $form=$s['form']->create($s['actor'],$this->scope,'event_signup','Event signup','event',$draft,$now,CorrelationId::generate());
        $v1=$s['form']->publish($s['actor'],$this->scope,$form,1,$now,CorrelationId::generate());
        $modified=['schema_version'=>1,'fields'=>[['key'=>'email','type'=>'email','label'=>'Email','required'=>true]]];
        $s['form']->save_draft($s['actor'],$this->scope,$form,1,$modified,$now,CorrelationId::generate());
        try {
            $s['form']->save_draft($s['actor'],$this->scope,$form,1,$draft,$now,CorrelationId::generate());
            self::fail('Stale draft revision accepted');
        } catch(RuntimeException $e) { self::assertStringContainsString('Stale',$e->getMessage()); }
        $v2=$s['form']->publish($s['actor'],$this->scope,$form,2,$now,CorrelationId::generate());
        $root=$s['forms']->find($this->scope,$form);
        self::assertSame(2,(int)$root['draft_revision']);
        self::assertNotSame($v1->to_string(),$v2->to_string());
        $versions=$this->db->rows('SELECT public_id, version, schema_json, checksum FROM %i WHERE form_id = %d ORDER BY version ASC',[$this->prefix.'form_versions',(int)$root['id']]);
        self::assertCount(2,$versions);
        self::assertSame('name',json_decode($versions[0]['schema_json'],true)['fields'][0]['key']);
        self::assertSame('email',json_decode($versions[1]['schema_json'],true)['fields'][0]['key']);
        foreach($versions as $version) self::assertSame(hash('sha256',$version['schema_json'],true),$version['checksum']);

        $consent=['schema_version'=>1,'fields'=>[['key'=>'photo','type'=>'consent','label'=>'Photo','required'=>true,'consent_definition_public_id'=>PublicId::generate()->to_string()]]];
        $with_consent=$s['form']->create($s['actor'],$this->scope,'consent_form','Consent','event',$consent,$now,CorrelationId::generate());
        $this->expectException(RuntimeException::class);
        $s['form']->publish($s['actor'],$this->scope,$with_consent,1,$now,CorrelationId::generate());
    }

    public function test_existing_event_configuration_and_manual_dst_occurrence(): void {
        $s=$this->services();
        if (!post_type_exists('uop_event')) register_post_type('uop_event',['public'=>true]);
        $post_id=wp_insert_post(['post_type'=>'uop_event','post_title'=>'Workshop','post_status'=>'draft'],true);
        self::assertIsInt($post_id);
        $uuid=$s['event']->configure($s['actor'],$this->scope,$post_id,'Europe/Berlin','2030-01-01 10:00:00',CorrelationId::generate());
        self::assertSame($uuid->to_binary(),$s['events']->find($this->scope,$post_id)['public_id']);
        $window=new OccurrenceWindow('2026-10-25T02:30:00+02:00','2026-10-25T03:30:00+01:00','Europe/Berlin');
        $occurrence=$s['event']->add_occurrence($s['actor'],$this->scope,$post_id,$window,'2030-01-01 10:00:00',CorrelationId::generate());
        $row=$this->db->rows('SELECT start_at, end_at, timezone FROM %i WHERE organization_id = %d AND public_id = %s',[$this->prefix.'event_occurrences',$this->scope->id,$occurrence->to_binary()]);
        self::assertSame('2026-10-25 00:30:00',$row[0]['start_at']);
        self::assertSame('2026-10-25 02:30:00',$row[0]['end_at']);
        self::assertSame('Europe/Berlin',$row[0]['timezone']);
        self::assertNull($s['events']->find(new OrgScope($this->scope->id+99),$post_id));
        // A user who passes the application's manager policy still must have
        // WordPress edit_post authority over this particular editorial event.
        $stranger = wp_create_user('uop_occ_view_' . bin2hex(random_bytes(4)), wp_generate_password(32), 'occ_' . bin2hex(random_bytes(4)) . '@example.invalid');
        self::assertIsInt($stranger);
        $stranger_actor = new Actor($stranger);
        $policy = new PolicyService(
            $s['people'],
            new DelegationRepository($this->db,$this->prefix),
            new AssignmentRepository($this->db,$this->prefix),
            static fn(int $user_id, string $cap): bool => true
        );
        $tx = new TransactionManager($this->db, static function(int $n): void {}, static function(\Throwable $e): void {});
        $guarded_event = new EventService(
            $s['events'],
            new OccurrenceRepository($this->db,$this->prefix),
            $policy,
            $tx,
            new AuditWriter($this->db,$this->prefix),
            new OutboxRepository($this->db,$this->prefix)
        );
        self::assertFalse(user_can($stranger, 'edit_post', $post_id));
        $before = $this->db->rows('SELECT id FROM %i WHERE event_post_id = %d', [$this->prefix.'event_occurrences',$post_id]);
        try {
            $guarded_event->add_occurrence($stranger_actor,$this->scope,$post_id,$window,'2030-01-01 10:00:00',CorrelationId::generate());
            self::fail('WordPress post permission was not enforced');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('outside authorized', $e->getMessage());
        }
        $after = $this->db->rows('SELECT id FROM %i WHERE event_post_id = %d', [$this->prefix.'event_occurrences',$post_id]);
        self::assertCount(count($before), $after);

    }
}
