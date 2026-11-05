<?php
namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use UOP\Application\Event\PostCommitPublisher;
use UOP\Application\Identity\{AccountDeletionListener, AccountLinkService, AssignmentService, DelegationService, PersonService};
use UOP\Application\Policy\{Actor, PolicyObject, PolicyService};
use UOP\Core\{CorrelationId, PublicId, TransactionManager};
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\{AssignmentRepository, AuditWriter, DelegationRepository, Installer, OutboxRepository, PersonRepository, RelationshipRepository, SchemaManifest, WpdbConnection};
use UOP\Infrastructure\Queue\OutboxDispatcher;

final class M2IdentityTest extends TestCase {
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

    private function fixture(): array {
        $people = new PersonRepository($this->db, $this->prefix);
        $relations = new RelationshipRepository($this->db, $this->prefix);
        $delegations = new DelegationRepository($this->db, $this->prefix);
        $assignments = new AssignmentRepository($this->db, $this->prefix);
        $admin = wp_create_user('uop_admin_' . bin2hex(random_bytes(4)), wp_generate_password(20), 'admin_' . bin2hex(random_bytes(4)) . '@example.invalid');
        self::assertIsInt($admin);
        $policy = new PolicyService($people, $delegations, $assignments, static fn(int $user_id, string $cap): bool => $user_id === $admin);
        $tx = new TransactionManager($this->db, static function (int $delay): void {}, static function (\Throwable $e): void {});
        $audit = new AuditWriter($this->db,$this->prefix);
        $outbox = new OutboxRepository($this->db,$this->prefix);
        $pub = new PostCommitPublisher($tx);
        return compact('people','relations','delegations','assignments','admin','policy','tx','audit','outbox','pub');
    }

    public function test_audited_guardian_delegation_and_wp_user_deletion(): void {
        $f=$this->fixture();
        $admin=new Actor($f['admin']);
        $now='2030-01-02 03:04:05';
        $people_service=new PersonService($f['people'],$f['policy'],$f['tx'],$f['audit'],$f['outbox'],$f['pub']);
        $parent=$people_service->create($admin,$this->scope,'Parent','family@example.invalid',$now,CorrelationId::generate());
        $child=$people_service->create($admin,$this->scope,'Child','family@example.invalid',$now,CorrelationId::generate());
        $subject=$f['people']->find($this->scope,$child);
        $parent_row=$f['people']->find($this->scope,$parent);
        self::assertNotNull($subject);self::assertNotNull($parent_row);
        $guardian=wp_create_user('uop_guard_' . bin2hex(random_bytes(4)), wp_generate_password(20), 'guard_' . bin2hex(random_bytes(4)) . '@example.invalid');
        self::assertIsInt($guardian);
        $link=new AccountLinkService($f['people'],$f['policy'],$f['tx'],$f['audit'],$f['outbox'],$f['pub']);
        $link->link($admin,$this->scope,$parent,$guardian,$now,CorrelationId::generate());
        self::assertSame($guardian,(int)$f['people']->find($this->scope,$parent)['wp_user_id']);
        self::assertNull($f['people']->find($this->scope,$child)['wp_user_id']);
        $rel_id=PublicId::generate();
        $f['relations']->create($this->scope,$rel_id,(int)$parent_row['id'],(int)$subject['id'],'guardian_of',$now);
        $child_object=new PolicyObject($this->scope->id,'person',(int)$subject['id']);
        self::assertFalse($f['policy']->can(new Actor($guardian),'person.view',$child_object)->allowed,'Relationship never conveys access');
        $service=new DelegationService($f['people'],$f['relations'],$f['delegations'],$this->db,$f['policy'],$f['tx'],$f['audit'],$f['outbox'],$f['pub']);
        $grant=$service->grant($admin,$this->scope,$guardian,$child,'registration_manage','organization',0,$rel_id,$now,CorrelationId::generate());
        self::assertTrue($f['policy']->can(new Actor($guardian),'person.view',$child_object)->allowed);
        $service->revoke($admin,$this->scope,$grant,$now,CorrelationId::generate());
        self::assertFalse($f['policy']->can(new Actor($guardian),'person.view',$child_object)->allowed,'Revocation applies on next decision');
        $service->grant($admin,$this->scope,$guardian,$child,'registration_manage','organization',0,$rel_id,$now,CorrelationId::generate());
        $f['assignments']->grant($this->scope,$guardian,'viewer','organization',0,'personal',$now);
        $deletion=new AccountDeletionListener($this->db,$this->prefix,$f['people'],$f['delegations'],$f['assignments'],$f['tx'],$f['audit'],$f['outbox'],$f['pub']);
        add_action('deleted_user',[$deletion,'handle'],10,1);
        require_once ABSPATH . 'wp-admin/includes/user.php';
        try { self::assertTrue(wp_delete_user($guardian)); } finally { remove_action('deleted_user',[$deletion,'handle'],10); }
        self::assertNull($f['people']->find($this->scope,$parent)['wp_user_id']);
        self::assertNotNull($f['people']->find($this->scope,$child));
        self::assertFalse($f['policy']->can(new Actor($guardian),'person.view',$child_object)->allowed);
        self::assertSame([], $f['assignments']->active_for($this->scope,$guardian));
        $events=$this->db->rows('SELECT id, event_uuid, event_name, published_at FROM %i WHERE organization_id = %d',[$this->prefix . 'domain_events',$this->scope->id]);
        self::assertGreaterThanOrEqual(7,count($events));
        $audit=$this->db->rows('SELECT action, data_json FROM %i WHERE organization_id = %d',[$this->prefix . 'audit_log',$this->scope->id]);
        self::assertGreaterThanOrEqual(7,count($audit));
        foreach ($audit as $entry) self::assertStringNotContainsString('family@example.invalid',(string)$entry['data_json']);
        $first=PublicId::from_binary($events[0]['event_uuid']);
        $delivered=0;
        $listener=static function () use (&$delivered): void { ++$delivered; };
        add_action('uop_domain_event',$listener);
        try {
            $dispatcher=new OutboxDispatcher($this->db,$f['outbox'],$this->prefix);
            $dispatcher->consume($this->scope->id,$first->to_string());
            $dispatcher->consume($this->scope->id,$first->to_string());
            self::assertSame(1,$delivered,'Duplicate worker invocation cannot republish completed event');
        } finally { remove_action('uop_domain_event',$listener); }
    }


    public function test_assignment_grant_and_revoke_are_scoped_and_audited(): void {
        $f=$this->fixture();
        $manager=new Actor($f['admin']);
        $target=wp_create_user('uop_target_' . bin2hex(random_bytes(4)),wp_generate_password(20),'target_' . bin2hex(random_bytes(4)) . '@example.invalid');
        self::assertIsInt($target);
        $service=new AssignmentService($f['assignments'],$this->db,$f['policy'],$f['tx'],$f['audit'],$f['outbox']);
        $service->grant($manager,$this->scope,$target,'viewer','organization',0,'personal','2030-01-02 03:04:05',CorrelationId::generate());
        $assignments=$f['assignments']->active_for($this->scope,$target);
        self::assertCount(1,$assignments);
        $id=(int)$assignments[0]['id'];
        try {
            $service->revoke($manager,new OrgScope($this->scope->id+100),$id,'2030-01-02 03:04:05',CorrelationId::generate());
            self::fail('Cross-org revocation succeeded');
        } catch (RuntimeException) {
            self::assertCount(1,$f['assignments']->active_for($this->scope,$target));
        }
        $service->revoke($manager,$this->scope,$id,'2030-01-02 03:04:05',CorrelationId::generate());
        self::assertSame([],$f['assignments']->active_for($this->scope,$target));
        $entries=$this->db->rows('SELECT action FROM %i WHERE organization_id = %d AND action IN (%s,%s)',[$this->prefix.'audit_log',$this->scope->id,'assignment.granted','assignment.revoked']);
        self::assertCount(2,$entries);
    }

    public function test_audit_and_outbox_share_rollback_boundary(): void {
        $f=$this->fixture();$event=PublicId::generate();$corr=CorrelationId::generate();
        $object=new PolicyObject($this->scope->id,'organization',$this->scope->id);
        try {
            $f['tx']->run(function () use ($f,$event,$corr,$object): void {
                $f['audit']->append($this->scope,new Actor(0),'test.rollback',$object,'success',$corr,$event);
                $f['outbox']->append($this->scope,$event,'person',1,'person.created',$corr,['status'=>'active']);
                throw new RuntimeException('Injected rollback');
            });
            self::fail('Rollback failed');
        } catch (RuntimeException $e) { self::assertSame('Injected rollback',$e->getMessage()); }
        self::assertSame([], $this->db->rows('SELECT id FROM %i WHERE event_uuid = %s',[$this->prefix . 'domain_events',$event->to_binary()]));
        self::assertSame([], $this->db->rows('SELECT id FROM %i WHERE event_uuid = %s',[$this->prefix . 'audit_log',$event->to_binary()]));
        $this->expectException(\InvalidArgumentException::class);
        $f['audit']->append($this->scope,new Actor(0),'test.metadata',$object,'success',$corr,null,['allergy'=>'secret']);
    }
}
