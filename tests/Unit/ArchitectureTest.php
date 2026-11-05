<?php
namespace UOP\Tests\Unit;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use UOP\Domain\Organization\OrgScope;

final class ArchitectureTest extends TestCase {
    private function unsafe_methods(string $class): array {
        $unsafe = [];
        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor()) continue;
            $parameters = $method->getParameters();
            $type = $parameters[0]->getType() ?? null;
            if (!$type instanceof \ReflectionNamedType || $type->getName() !== OrgScope::class || $type->allowsNull() || $parameters[0]->isOptional()) $unsafe[] = $method->getName();
        }
        return $unsafe;
    }
    public function test_every_tenant_repository_requires_non_optional_org_scope(): void {
        $files = glob(dirname(__DIR__, 2) . '/src/Infrastructure/Database/*Repository.php');
        self::assertNotEmpty($files);
        foreach ($files as $file) {
            $class = 'UOP\\Infrastructure\\Database\\' . basename($file, '.php');
            self::assertSame([], $this->unsafe_methods($class), $class);
        }
    }
    public function test_architecture_guard_detects_an_unsafe_signature(): void {
        $unsafe = new class { public function find(int $id): void {} };
        self::assertSame(['find'], $this->unsafe_methods($unsafe::class));
    }
    public function test_no_wildcard_reads_or_out_of_scope_namespaces(): void {
        $root = dirname(__DIR__, 2) . '/src';
        $count = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') continue;
            ++$count;
            self::assertDoesNotMatchRegularExpression('/SELECT\s+(?:\w+\.)?\*/i', file_get_contents($file->getPathname()));
        }
        self::assertGreaterThan(0, $count);
        foreach (['People','Events','Forms','Registration','Capacity','Consent','Privacy','Payments','Documents','Tasks','Signatures'] as $domain) self::assertDirectoryDoesNotExist($root . '/Domain/' . $domain);
    }
}
