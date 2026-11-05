<?php
namespace UOP\Tests\Unit;

use Closure;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UOP\Core\{PublicId, CorrelationId, SystemClock, TransactionManager};
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\{Connection, DatabaseException, PageRequest, ScopedRepository, SchemaManifest};

final class RecordingConnection implements Connection {
    public function identity(): object { return $this; }
    public function apply_schema(string $ddl, string $lock_name): void { throw new LogicException('DDL is not part of this unit fixture.'); }
    public array $statements = [];
    public function execute(string $sql, array $args = []): int { $this->statements[] = [$sql, $args]; return 1; }
    public function rows(string $sql, array $args = []): array { $this->execute($sql, $args); return []; }
}

final class PersistenceTest extends TestCase {
    public function test_public_id_binary_roundtrip_version_variant_and_uniqueness(): void {
        $seen = [];
        for ($i = 0; $i < 1000; ++$i) {
            $id = PublicId::generate();
            self::assertSame(16, strlen($id->to_binary()));
            self::assertMatchesRegularExpression('/^[a-f0-9-]{14}4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $id->to_string());
            self::assertSame($id->to_string(), PublicId::from_binary($id->to_binary())->to_string());
            self::assertSame($id->to_binary(), PublicId::from_string($id->to_string())->to_binary());
            self::assertArrayNotHasKey($id->to_string(), $seen);
            $seen[$id->to_string()] = true;
        }
        $correlation = CorrelationId::generate();
        self::assertSame($correlation->to_string(), PublicId::from_binary($correlation->to_binary())->to_string());
    }
    public function test_invalid_ids_fail_before_any_repository_query(): void {
        $db = new RecordingConnection();
        $repo = new class($db, 'wp_uop_persons', ['id', 'public_id', 'organization_id']) extends ScopedRepository {};
        foreach (['', '1', '../person', '00000000-0000-0000-0000-000000000000', '550E8400-E29B-41D4-A716-446655440000', "550e8400-e29b-41d4-a716-446655440000\n"] as $raw) {
            try { $repo->find(new OrgScope(1), PublicId::from_string($raw)); self::fail('Invalid UUID accepted'); }
            catch (InvalidArgumentException) { self::assertSame([], $db->statements); }
        }
        foreach (['', random_bytes(15), str_repeat("\0", 16)] as $binary) {
            try { PublicId::from_binary($binary); self::fail('Invalid bytes accepted'); }
            catch (InvalidArgumentException) { self::assertSame([], $db->statements); }
        }
    }
    public function test_clock_is_utc_even_when_process_timezone_is_not(): void {
        $zone = date_default_timezone_get();
        try {
            date_default_timezone_set('Pacific/Auckland');
            self::assertSame('UTC', (new SystemClock())->now()->getTimezone()->getName());
        } finally { date_default_timezone_set($zone); }
    }
    public function test_deadlock_retries_whole_work_and_only_successful_callbacks(): void {
        $db = new RecordingConnection();
        $pauses = []; $diagnostics = []; $callbacks = []; $attempt = 0;
        $tx = new TransactionManager($db, function ($delay) use (&$pauses) { $pauses[] = $delay; }, function ($error) use (&$diagnostics) { $diagnostics[] = $error; });
        $result = $tx->run(function () use ($tx, &$attempt, &$callbacks) {
            ++$attempt;
            $captured = $attempt;
            $tx->after_commit(function () use (&$callbacks, $captured) { $callbacks[] = $captured; });
            if ($attempt <= 3) throw new DatabaseException('Deadlock fixture', 1213);
            return 'committed';
        });
        self::assertSame('committed', $result);
        self::assertSame(4, $attempt);
        self::assertCount(3, $pauses);
        self::assertSame([4], $callbacks);
        self::assertSame(['START TRANSACTION','ROLLBACK','START TRANSACTION','ROLLBACK','START TRANSACTION','ROLLBACK','START TRANSACTION','COMMIT'], array_column($db->statements, 0));
    }
    public function test_deadlock_exhaustion_and_non_deadlock_do_not_retry_forever(): void {
        foreach ([1213 => 4, 1205 => 1, 1062 => 1] as $code => $expected) {
            $db = new RecordingConnection(); $attempt = 0;
            $tx = new TransactionManager($db, static function () {}, static function () {});
            try {
                $tx->run(function () use ($code, &$attempt) { ++$attempt; throw new DatabaseException('fixture', $code); });
                self::fail('Failure swallowed');
            } catch (DatabaseException $error) { self::assertSame($code, $error->getCode()); }
            self::assertSame($expected, $attempt);
            self::assertSame('ROLLBACK', end($db->statements)[0]);
        }
    }
    public function test_post_commit_failures_cannot_change_success_or_trigger_retry(): void {
        $db = new RecordingConnection();
        $tx = new TransactionManager($db, static function () {}, static function () { throw new RuntimeException('diagnostic failure'); });
        $result = $tx->run(function () use ($tx, $db) {
            $tx->after_commit(function () use ($db) {
                self::assertSame('COMMIT', end($db->statements)[0]);
                throw new RuntimeException('hook failure');
            });
            return 42;
        });
        self::assertSame(42, $result);
        self::assertCount(2, $db->statements);
    }
    public function test_nested_transaction_rolls_back_and_callbacks_are_discarded(): void {
        $db = new RecordingConnection(); $called = false;
        $tx = new TransactionManager($db, static function () {}, static function () {});
        try {
            $tx->run(function () use ($tx, &$called) {
                $tx->after_commit(function () use (&$called) { $called = true; });
                $tx->run(static fn () => null);
            });
            self::fail('Nested transaction accepted');
        } catch (LogicException) { self::assertFalse($called); }
        self::assertSame(['START TRANSACTION', 'ROLLBACK'], array_column($db->statements, 0));
        self::assertSame(1, $tx->run(static fn () => 1));
        $this->expectException(LogicException::class);
        $tx->after_commit(static function () {});
    }
    public function test_pagination_sort_filter_scope_and_columns_are_not_bypassable(): void {
        $db = new RecordingConnection();
        $repo = new class($db, 'wp_uop_persons', ['id', 'public_id', 'organization_id'], ['status']) extends ScopedRepository {};
        $repo->page(new OrgScope(7), new PageRequest(25, 100, 'id', 'DESC'), ['status' => "' OR 1=1 --"]);
        [$sql, $args] = $db->statements[0];
        self::assertStringContainsString('WHERE organization_id = %d', $sql);
        self::assertStringContainsString('AND id < %d ORDER BY id DESC LIMIT %d', $sql);
        self::assertStringNotContainsString('OR 1=1', $sql);
        self::assertContains("' OR 1=1 --", $args);
        foreach ([[0], [101], [50,-1], [50,0,'display_name; DROP TABLE people'], [50,0,'id','random']] as $request) {
            try { new PageRequest(...$request); self::fail('Invalid query accepted'); }
            catch (InvalidArgumentException) { self::assertCount(1, $db->statements); }
        }
        try { $repo->page(new OrgScope(7), new PageRequest(), ['organization_id' => 8]); self::fail('Scope override accepted'); }
        catch (InvalidArgumentException) { self::assertCount(1, $db->statements); }
        $this->expectException(InvalidArgumentException::class);
        new OrgScope(0);
    }
    public function test_normative_schema_count_and_email_additions(): void {
        $schema = new SchemaManifest(dirname(__DIR__, 2) . '/schema/manifest.json');
        self::assertCount(28, $schema->tables());
        foreach (['tasks','documents','payments','signatures','attendance','automations'] as $name) self::assertArrayNotHasKey($name, $schema->tables());
        foreach (['template_revision','template_hash','locale'] as $column) self::assertArrayHasKey($column, $schema->expected('email_messages')['columns']);
        self::assertSame(['organization_id','wp_user_id'], $schema->expected('persons')['indexes']['uq_org_wp_user']['columns']);
        self::assertFalse($schema->expected('persons')['indexes']['ix_primary_email']['unique']);
    }
}
