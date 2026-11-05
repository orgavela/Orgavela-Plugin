<?php
namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use UOP\Core\{Bootstrap, Clock, PublicId, SystemClock, TransactionManager};
use UOP\Domain\Organization\OrgScope;
use UOP\Infrastructure\Database\{Connection, DatabaseException, InitialMigration, Installer, MigrationContext, MigrationRegistry, MigrationRunner, MigrationState, PageRequest, SchemaInspector, SchemaManifest, ScopedRepository, WpdbConnection};

final class PersistenceTest extends TestCase {
    private WpdbConnection $db;
    private MigrationState $state;
    private SchemaManifest $manifest;
    private SchemaInspector $inspector;
    private string $prefix;
    protected function setUp(): void {
        global $wpdb;
        Bootstrap::deactivate();
        $this->db = new WpdbConnection($wpdb);
        $this->state = new MigrationState($this->db, $wpdb->options);
        $this->manifest = new SchemaManifest(dirname(__DIR__, 2) . '/schema/manifest.json');
        $this->prefix = $wpdb->prefix . 'uop_';
        $this->inspector = new SchemaInspector($this->db, $this->manifest, $this->prefix, $wpdb->collate);
        // This bootstrap only loads with explicit disposable-database opt-in.
        foreach (array_keys($this->manifest->tables()) as $name) $this->db->execute('DROP TABLE IF EXISTS %i', [$this->prefix . $name]);
        foreach (['uop_db_version','uop_data_version','uop_migration_progress_1','uop_migration_progress_99','uop_migration_status','uop_default_organization_id'] as $key) delete_option($key);
    }
    private function runner(?Connection $db = null, ?Clock $clock = null): MigrationRunner {
        global $wpdb;
        $db ??= $this->db;
        $state = new MigrationState($db, $wpdb->options);
        $tx = new TransactionManager($db, static function ($delay) { usleep($delay); }, static function () {});
        $migration = new InitialMigration($db, $state, $this->manifest, $this->inspector, $clock ?? new SystemClock(), $this->prefix, $wpdb->get_charset_collate());
        return new MigrationRunner($db, $state, new MigrationRegistry([$migration]), $tx, Bootstrap::errors(...), Installer::lock_name());
    }
    private function seed_org(string $slug): int {
        $time = '2030-01-02 03:04:05';
        $this->db->execute('INSERT INTO %i (public_id,name,slug,status,created_at,updated_at) VALUES (%s,%s,%s,%s,%s,%s)', [$this->prefix . 'organizations',PublicId::generate()->to_binary(),$slug,$slug,'active',$time,$time]);
        return (int)$this->db->rows('SELECT id FROM %i WHERE slug = %s', [$this->prefix . 'organizations',$slug])[0]['id'];
    }
    public function test_fresh_install_all_28_tables_exact_columns_indexes_and_idempotence(): void {
        $clock = new class implements Clock {
            public function now(): \DateTimeImmutable { return new \DateTimeImmutable('2030-01-02T03:04:05+00:00'); }
        };
        $this->runner(clock: $clock)->run();
        $this->inspector->verify_all();
        self::assertSame(1, (int)$this->state->read('uop_db_version'));
        self::assertSame(1, (int)$this->state->read('uop_data_version'));
        $first = $this->db->rows('SELECT id,public_id,created_at FROM %i', [$this->prefix . 'organizations']);
        self::assertCount(1, $first);
        self::assertSame('2030-01-02 03:04:05', $first[0]['created_at']);
        $definitions = [];
        foreach (array_keys($this->manifest->tables()) as $table) $definitions[$table] = $this->db->rows('SHOW CREATE TABLE %i', [$this->prefix . $table]);
        $this->runner()->run();
        self::assertSame($first, $this->db->rows('SELECT id,public_id,created_at FROM %i', [$this->prefix . 'organizations']));
        foreach (array_keys($this->manifest->tables()) as $table) self::assertSame($definitions[$table], $this->db->rows('SHOW CREATE TABLE %i', [$this->prefix . $table]));
        self::assertSame('good', Installer::health()['status']);
    }
    public function test_interruption_after_committed_ddl_resumes_without_duplicate_seed(): void {
        $interrupted = false;
        $fault = new class($this->db, $interrupted) implements Connection {
            private int $checkpoints = 0;
            public function __construct(private Connection $db, private bool &$interrupted) {}
            public function identity(): object { return $this->db->identity(); }
            public function apply_schema(string $ddl, string $lock_name): void { $this->db->apply_schema($ddl, $lock_name); }
            public function execute(string $sql, array $args = []): int {
                if (in_array('uop_migration_progress_1', $args, true) && ++$this->checkpoints === 8 && !$this->interrupted) {
                    $this->interrupted = true;
                    throw new RuntimeException('Simulated interruption after DDL and before checkpoint');
                }
                return $this->db->execute($sql, $args);
            }
            public function rows(string $sql, array $args = []): array { return $this->db->rows($sql, $args); }
        };
        try { $this->runner($fault)->run(); self::fail('Interruption not injected'); }
        catch (RuntimeException $error) { self::assertStringContainsString('Simulated interruption', $error->getMessage()); }
        self::assertTrue($interrupted);
        self::assertSame(0, (int)$this->state->read('uop_db_version', 0));
        self::assertCount(7, $this->state->read('uop_migration_progress_1'));
        self::assertSame('failed', $this->state->read('uop_migration_status')['status']);
        self::assertSame('critical', Installer::health()['status']);
        $this->runner()->run();
        $this->inspector->verify_all();
        self::assertCount(1, $this->db->rows('SELECT id FROM %i', [$this->prefix . 'organizations']));
        self::assertSame([], $this->state->read('uop_migration_progress_1'));
    }
    public function test_competing_connection_cannot_run_migrations_and_lock_recovers_on_disconnect(): void {
        global $wpdb;
        $other = new \wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $other->query($other->prepare('SELECT GET_LOCK(%s,0)', Installer::lock_name()));
        try {
            try { $this->runner()->run(); self::fail('Concurrent runner acquired held lock'); }
            catch (RuntimeException $error) { self::assertStringContainsString('Another migration runner', $error->getMessage()); }
            self::assertSame(0, (int)$this->state->read('uop_db_version', 0));
            self::assertSame([], $this->db->rows('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND LEFT(TABLE_NAME,%d) = %s', [strlen($this->prefix),$this->prefix]));
        } finally { $other->close(); }
        $this->runner()->run();
        $this->inspector->verify_all();
        self::assertSame(1, (int)$this->state->read('uop_db_version'));
    }
    public function test_transactional_step_rolls_back_and_can_resume(): void {
        $this->runner()->run();
        $tx = new TransactionManager($this->db, static function () {}, static function () {});
        $context = new MigrationContext($this->state, $tx, 99);
        try {
            $context->step('rollback_probe', function () { $this->seed_org('rollback-probe'); }, static function () { throw new RuntimeException('verification failure'); }, true);
            self::fail('Failing step committed');
        } catch (RuntimeException) {}
        self::assertSame([], $this->db->rows('SELECT id FROM %i WHERE slug = %s', [$this->prefix . 'organizations','rollback-probe']));
        self::assertSame([], $this->state->read('uop_migration_progress_99', []));
        $context->step('rollback_probe', function () { $this->seed_org('rollback-probe'); }, static function () {}, true);
        self::assertSame(['rollback_probe' => true], $this->state->read('uop_migration_progress_99'));
        $context->step('rollback_probe', static function () { throw new RuntimeException('must not rerun'); }, static function () {}, true);
        self::assertCount(1, $this->db->rows('SELECT id FROM %i WHERE slug = %s', [$this->prefix . 'organizations','rollback-probe']));
    }
    public function test_scoped_repository_blocks_cross_org_and_preserves_keyset_pages(): void {
        $this->runner()->run();
        $one = (int)$this->state->read('uop_default_organization_id');
        $two = $this->seed_org('second');
        $ids = [];
        foreach ([$one,$two,$one] as $index => $org) {
            $id = PublicId::generate(); $ids[] = $id;
            $this->db->execute('INSERT INTO %i (public_id,organization_id,display_name,primary_email,status,created_at,updated_at) VALUES (%s,%d,%s,%s,%s,%s,%s)', [$this->prefix . 'persons',$id->to_binary(),$org,'Fixture ' . $index,'shared@example.invalid','active','2030-01-01 00:00:00','2030-01-01 00:00:00']);
        }
        $repo = new class($this->db, $this->prefix . 'persons', ['id','public_id','organization_id','display_name'], ['status']) extends ScopedRepository {};
        self::assertNull($repo->find(new OrgScope($one), $ids[1]));
        self::assertSame('Fixture 1', $repo->find(new OrgScope($two), $ids[1])['display_name']);
        $page1 = $repo->page(new OrgScope($one), new PageRequest(1), ['status'=>'active']);
        $page2 = $repo->page(new OrgScope($one), new PageRequest(1,(int)$page1[0]['id']), ['status'=>'active']);
        self::assertCount(1,$page1); self::assertCount(1,$page2);
        self::assertSame('Fixture 0',$page1[0]['display_name']);
        self::assertSame('Fixture 2',$page2[0]['display_name']);
        self::assertSame([], $repo->page(new OrgScope($one), new PageRequest(1,(int)$page2[0]['id'])));
        self::assertSame([], $repo->page(new OrgScope($two + 100), new PageRequest()));
    }
    public function test_unsupported_environment_never_creates_operational_tables(): void {
        global $wp_version;
        $original = $wp_version;
        try {
            $wp_version = '6.8';
            try { $this->runner()->run(); self::fail('Unsupported environment allowed'); }
            catch (RuntimeException $error) { self::assertStringContainsString('unsupported', $error->getMessage()); }
            self::assertSame([], $this->db->rows('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND LEFT(TABLE_NAME,%d) = %s', [strlen($this->prefix),$this->prefix]));
            self::assertSame(0,(int)$this->state->read('uop_db_version',0));
            self::assertNull($this->state->read('uop_migration_status'));
        } finally { $wp_version = $original; }
    }
    public function test_schema_drift_is_detected_instead_of_silently_accepted(): void {
        $this->runner()->run();
        $this->db->execute('ALTER TABLE %i DROP INDEX uq_org_wp_user', [$this->prefix . 'persons']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Schema column or index drift');
        $this->inspector->verify_all();
    }
    public function test_unique_constraints_and_database_errors_are_not_silenced(): void {
        $this->runner()->run();
        try { $this->seed_org('default'); self::fail('Unique slug constraint missing'); }
        catch (DatabaseException $error) {
            self::assertSame(1062,$error->getCode());
            self::assertSame('Database operation failed.',$error->getMessage());
        }
        $this->inspector->verify_all();
    }
    public function test_nontransactional_wordpress_options_are_rejected_before_schema_creation(): void {
        global $wpdb;
        $this->db->execute('ALTER TABLE %i ENGINE=MyISAM', [$wpdb->options]);
        try {
            self::assertContains('innodb_required', Bootstrap::errors());
            try { $this->runner()->run(); self::fail('Nontransactional checkpoint store accepted'); }
            catch (RuntimeException $error) { self::assertStringContainsString('unsupported', $error->getMessage()); }
            self::assertNull($this->state->read('uop_migration_status'));
        } finally { $this->db->execute('ALTER TABLE %i ENGINE=InnoDB', [$wpdb->options]); }
    }
    public function test_connection_loss_does_not_replay_work_in_autocommit(): void {
        $this->runner()->run();
        $session = new \wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $connection = new WpdbConnection($session);
        $id = (int)$connection->rows('SELECT CONNECTION_ID() AS id')[0]['id'];
        $retries = $session->__get('reconnect_retries');
        $tx = new TransactionManager($connection, static function () { self::fail('Connection loss must not retry'); }, static function () {});
        try {
            $tx->run(function () use ($connection, $id) {
                $connection->execute('INSERT INTO %i (public_id,name,slug,status,created_at,updated_at) VALUES (%s,%s,%s,%s,%s,%s)', [$this->prefix . 'organizations',PublicId::generate()->to_binary(),'Probe','disconnect-probe','active','2030-01-01 00:00:00','2030-01-01 00:00:00']);
                $this->db->execute('KILL CONNECTION %d', [$id]);
                $connection->execute('UPDATE %i SET name = %s WHERE slug = %s', [$this->prefix . 'organizations','Must not commit','disconnect-probe']);
            });
            self::fail('Disconnected transaction succeeded');
        } catch (DatabaseException $error) { self::assertSame(2006, $error->getCode()); }
        finally { $session->close(); }
        self::assertSame($retries, $session->__get('reconnect_retries'));
        self::assertSame([], $this->db->rows('SELECT id FROM %i WHERE slug = %s', [$this->prefix . 'organizations','disconnect-probe']));
    }
    public function test_upgrade_path_outside_activation_and_deactivation_preserves_seed(): void {
        Bootstrap::boot();
        $this->inspector->verify_all();
        $id = $this->state->read('uop_default_organization_id');
        Bootstrap::deactivate();
        Bootstrap::boot();
        self::assertSame($id, $this->state->read('uop_default_organization_id'));
        self::assertCount(1, $this->db->rows('SELECT id FROM %i', [$this->prefix . 'organizations']));
    }

    public static function ddl_loss_cases(): array { return [['CREATE'], ['ALTER']]; }

    #[\PHPUnit\Framework\Attributes\DataProvider('ddl_loss_cases')]
    public function test_lock_loss_during_dbdelta_never_replays_create_or_alter_and_can_resume(string $operation): void {
        global $wpdb;
        $original = $wpdb;
        $table = $this->prefix . 'organizations';
        $before = null;
        if ($operation === 'ALTER') {
            $this->db->execute($this->manifest->ddl('organizations', $this->prefix, $wpdb->get_charset_collate()));
            $this->db->execute('ALTER TABLE %i DROP INDEX uq_public_id', [$table]);
            $before = $this->db->rows('SHOW CREATE TABLE %i', [$table]);
        }
        $worker = new \wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $worker->set_prefix($original->prefix);
        $wpdb = $worker;
        $runner = Installer::runner();
        $lock = Installer::lock_name();
        $session = $worker->__get('dbh')->thread_id;
        $killer = $original->__get('dbh');
        $injected = false;
        $mode = (new \mysqli_driver())->report_mode;
        $retries = $worker->__get('reconnect_retries');
        $filter = function($sql) use ($operation, $table, $killer, $session, $lock, &$injected) {
            if (!$injected && str_starts_with($sql, $operation . ' TABLE ' . $table)) {
                $injected = true;
                $killer->query('KILL CONNECTION ' . $session);
                self::assertSame('1', (string)$killer->query("SELECT GET_LOCK('" . $killer->real_escape_string($lock) . "',2) AS owned")->fetch_assoc()['owned']);
            }
            return $sql;
        };
        add_filter('query', $filter);
        try {
            try { $runner->run(); self::fail('Lost lock was accepted'); }
            catch (DatabaseException $error) { self::assertContains($error->getCode(), [2006, 2013]); }
            self::assertTrue($injected);
            self::assertSame($retries, $worker->__get('reconnect_retries'));
            self::assertSame($mode, (new \mysqli_driver())->report_mode);
        } finally {
            remove_filter('query', $filter);
            $wpdb = $original;
            $worker->close();
            $killer->query("SELECT RELEASE_LOCK('" . $killer->real_escape_string($lock) . "')");
        }
        if ($before === null) {
            self::assertSame([], $this->db->rows('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', [$table]));
        } else {
            self::assertSame($before, $this->db->rows('SHOW CREATE TABLE %i', [$table]));
        }
        self::assertSame($before === null ? [] : [['TABLE_NAME'=>$table]], $this->db->rows('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,%d)=%s ORDER BY TABLE_NAME', [strlen($this->prefix),$this->prefix]));
        self::assertSame(0, (int)$this->state->read('uop_db_version', 0));
        self::assertSame([], $this->state->read('uop_migration_progress_1', []));
        self::assertSame('critical', Installer::health()['status']);
        Installer::runner()->run();
        $this->inspector->verify_all();
        self::assertSame('good', Installer::health()['status']);
    }

    public function test_two_transaction_managers_share_physical_connection_ownership(): void {
        global $wpdb;
        $this->runner()->run();
        foreach ([$this->db, new WpdbConnection($wpdb)] as $connection) {
            $outer = new TransactionManager($this->db, static function(){}, static function(){});
            $inner = new TransactionManager($connection, static function(){}, static function(){});
            $entered = false;
            try {
                $outer->run(function() use ($inner, &$entered) {
                    $this->seed_org('outer-rollback');
                    $inner->run(function() use (&$entered) { $entered=true; $this->seed_org('inner-rollback'); });
                    throw new RuntimeException('Outer failure');
                });
                self::fail('Nested transaction accepted');
            } catch (\LogicException $error) { self::assertStringContainsString('Nested', $error->getMessage()); }
            self::assertFalse($entered);
            self::assertSame([], $this->db->rows('SELECT id FROM %i WHERE slug IN (%s,%s)', [$this->prefix.'organizations','outer-rollback','inner-rollback']));
            self::assertSame(42, $inner->run(static fn()=>42));
        }
    }

    public function test_activation_recovery_and_health_reject_drift_at_current_version(): void {
        $this->runner()->run();
        $this->db->execute('ALTER TABLE %i DROP INDEX uq_org_wp_user', [$this->prefix.'persons']);
        self::assertSame('critical', Installer::health()['status']);
        $handler = static fn()=>static function($message){ throw new RuntimeException($message); };
        add_filter('wp_die_handler',$handler);
        try {
            try { Bootstrap::activate(); self::fail('Activation accepted drift'); }
            catch (RuntimeException $error) { self::assertStringContainsString('migration failed', $error->getMessage()); }
        } finally { remove_filter('wp_die_handler',$handler); }
        self::assertSame(1,(int)$this->state->read('uop_db_version'));
        self::assertSame('failed',$this->state->read('uop_migration_status')['status']);
        self::assertSame('critical', Installer::health()['status']);
        self::assertStringNotContainsString('ALTER TABLE', Installer::health()['description']);
        try { Installer::runner()->run(); self::fail('Recovery accepted drift'); }
        catch (RuntimeException $error) { self::assertSame('Schema column or index drift.', $error->getMessage()); }
        $this->db->execute('ALTER TABLE %i ADD UNIQUE KEY uq_org_wp_user (organization_id, wp_user_id)', [$this->prefix.'persons']);
        Installer::runner()->run();
        self::assertSame('good', Installer::health()['status']);
    }
}
