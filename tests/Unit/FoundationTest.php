<?php
namespace UOP\Tests\Unit;

use LogicException;
use PHPUnit\Framework\TestCase;
use UOP\Core\EnvironmentChecker;
use UOP\Core\ServiceContainer;
use UOP\Core\Kernel;
use UOP\Extension\ModuleInterface;
use UOP\Extension\ModuleRegistry;

final class FoundationTest extends TestCase {
    public function test_environment_matrix_and_fail_closed_parsing(): void {
        $gate = new EnvironmentChecker();
        foreach (['8.3.0', '8.4.0', '8.5.0'] as $php) {
            foreach (['8.0.40', '10.11.14-MariaDB', '5.5.5-10.11.14-MariaDB'] as $db) {
                self::assertSame([], $gate->errors($php, '6.9', $db, true, true));
            }
        }
        self::assertSame(['php_minimum', 'wordpress_minimum', 'database_minimum', 'innodb_required', 'utf8mb4_required'], $gate->errors('8.2.0', '6.8', '5.7.44', false, false));
        foreach (['10.10.9-MariaDB', '', 'unknown', 'PostgreSQL 16'] as $db) {
            self::assertContains('database_minimum', $gate->errors('8.3', '6.9', $db, true, true));
        }
    }
    public function test_services_are_lazy_shared_and_unknown_services_fail(): void {
        $container = new ServiceContainer();
        $calls = 0;
        $container->set('service', function () use (&$calls) { ++$calls; return new \stdClass(); });
        self::assertSame(0, $calls);
        self::assertSame($container->get('service'), $container->get('service'));
        self::assertSame(1, $calls);
        $this->expectException(LogicException::class);
        $container->get('missing');
    }
    public function test_cycles_fail_and_resolution_state_is_cleaned(): void {
        $container = new ServiceContainer();
        $container->set('a', fn ($c) => $c->get('b'));
        $container->set('b', fn ($c) => $c->get('a'));
        for ($i = 0; $i < 2; ++$i) {
            try { $container->get('a'); self::fail('Cycle accepted'); }
            catch (LogicException $e) { self::assertStringContainsString('circular', $e->getMessage()); }
        }
    }
    public function test_kernel_composes_modules_once_and_rejects_duplicate_keys(): void {
        $registry = new ModuleRegistry();
        $module = new class implements ModuleInterface {
            public int $calls = 0;
            public function key(): string { return 'foundation'; }
            public function register(ServiceContainer $container): void { ++$this->calls; }
        };
        $registry->add($module);
        $kernel = new Kernel(new ServiceContainer(), $registry);
        $kernel->boot(); $kernel->boot();
        self::assertSame(1, $module->calls);
        $this->expectException(LogicException::class);
        $registry->add($module);
    }
}
