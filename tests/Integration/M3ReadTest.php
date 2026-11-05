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
    }
}
