<?php
namespace UOP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UOP\Application\Policy\{Actor, Decision, FieldDefinition, PolicyService, ProjectionService, PolicyObject};
use UOP\Core\PublicId;
use UOP\Infrastructure\Database\{AssignmentRepository, Connection, DelegationRepository, PersonRepository};

final class PolicyFixtureConnection implements Connection {
    public array $people = [];
    public array $assignments = [];
    public array $delegations = [];
    public function identity(): object { return $this; }
    public function apply_schema(string $ddl, string $lock_name): void { throw new \LogicException('No DDL'); }
    public function execute(string $sql, array $args = []): int { return 1; }
    public function rows(string $sql, array $args = []): array {
        if (str_contains($sql, 'FROM %i WHERE organization_id = %d AND wp_user_id = %d')) {
            return array_values(array_filter($this->people, static fn($p) => $p['organization_id'] === $args[1] && $p['wp_user_id'] === $args[2]));
        }
        if (str_contains($sql, 'FROM %i WHERE organization_id = %d AND user_id = %d')) {
            return array_values(array_filter($this->assignments, static fn($a) => $a['organization_id'] === $args[1] && $a['user_id'] === $args[2] && $a['status'] === 'active'));
        }
        if (str_contains($sql, 'AND subject_person_id = %d AND permission_set = %s')) {
            return array_values(array_filter($this->delegations, static fn($d) => $d['organization_id'] === $args[1] && $d['actor_user_id'] === $args[2] && $d['subject_person_id'] === $args[3] && $d['permission_set'] === $args[4] && ($d['scope_type'] === 'organization' || $d['scope_id'] === $args[5]) && $d['status'] === 'active'));
        }
        return [];
    }
}

final class PolicyTest extends TestCase {
    private function policy(PolicyFixtureConnection $db, array $capabilities): PolicyService {
        return new PolicyService(
            new PersonRepository($db,'wp_uop_'),
            new DelegationRepository($db,'wp_uop_'),
            new AssignmentRepository($db,'wp_uop_'),
            static fn(int $actor, string $cap): bool => in_array($cap, $capabilities[$actor] ?? [], true)
        );
    }

    public function test_relationship_is_not_permission_and_cross_org_isolation(): void {
        $db = new PolicyFixtureConnection();
        $policy = $this->policy($db, []);
        $child = new PolicyObject(1, 'person', 40);
        self::assertFalse($policy->can(new Actor(7), 'person.view', $child)->allowed);
        $db->delegations[] = ['organization_id'=>1,'actor_user_id'=>7,'subject_person_id'=>40,'permission_set'=>'registration_manage','scope_type'=>'organization','scope_id'=>0,'status'=>'active'];
        self::assertSame('ALLOW_DELEGATION', $policy->can(new Actor(7),'person.view',$child)->reason);
        self::assertFalse($policy->can(new Actor(7),'person.edit',$child)->allowed, 'Registration grant must not provide edit authority');
        self::assertFalse($policy->can(new Actor(7),'person.view',new PolicyObject(2,'person',40))->allowed);
        $db->delegations[0]['status'] = 'revoked';
        self::assertFalse($policy->can(new Actor(7),'person.view',$child)->allowed);
    }

    public function test_staff_event_scope_and_sensitivity_reprojection(): void {
        $db = new PolicyFixtureConnection();
        $db->assignments[] = ['organization_id'=>1,'user_id'=>8,'role_key'=>'event_manager','scope_type'=>'event','scope_id'=>10,'sensitivity_ceiling'=>'personal','status'=>'active'];
        $caps = [8=>['uop_view_people', 'uop_view_registrations', 'uop_view_sensitive_data']];
        $policy = $this->policy($db,$caps);
        $same = new PolicyObject(1, 'registration', 100, 40, 10);
        $other = new PolicyObject(1, 'registration', 101, 40, 11);
        self::assertTrue($policy->can(new Actor(8), 'registration.view', $same)->allowed);
        self::assertFalse($policy->can(new Actor(8), 'registration.view', $other)->allowed);
        $fields = [
            'name' => new FieldDefinition('name','personal',true,true,true,true),
            'allergy' => new FieldDefinition('allergy','medical',true,true,true,true),
        ];
        $public_id = PublicId::generate();
        $projector = new ProjectionService($policy);
        $values = ['name'=>'Child', 'allergy'=>'secret','raw_unlisted'=>'must never escape'];
        foreach (['ADMIN', 'PORTAL', 'REST', 'CSV'] as $channel) {
            $view = $projector->project(new Actor(8), 'registration.view', $same, $public_id, $fields, $values);
            self::assertSame(['public_id'=>$public_id->to_string(), 'fields'=>['name'=>'Child']], $view, $channel);
            self::assertNull($projector->project(new Actor(8),'registration.view',$other,$public_id,$fields,$values));
        }
    }

    public function test_self_field_flags_unknown_actions_and_unknown_sensitivity_deny(): void {
        $db = new PolicyFixtureConnection();
        $db->people[] = ['organization_id'=>1,'id'=>13,'wp_user_id'=>6,'status'=>'active'];
        $policy = $this->policy($db,[]);
        $actor = new Actor(6);
        $person = new PolicyObject(1,'person',13);
        self::assertSame('ALLOW_SELF', $policy->can($actor,'person.view',$person)->reason);
        self::assertFalse($policy->can($actor,'person.edit',$person,new FieldDefinition('private','medical',true,false,true,false))->allowed);
        self::assertTrue($policy->can($actor,'person.view',$person,new FieldDefinition('private','medical',true,false,true,false))->allowed);
        self::assertFalse($policy->can($actor,'person.view',$person,new FieldDefinition('unknown','not-classified',true,true,true,true))->allowed);
        self::assertFalse($policy->can($actor,'undefined.action',$person)->allowed);
        self::assertSame('DENY_UNAUTHENTICATED',$policy->can(new Actor(0),'person.view',$person)->reason);
    }
}
