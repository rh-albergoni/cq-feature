<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Cq\CqFeature\Config\CqFeatureConfig;
use Cq\CqFeature\Services\PathResolver;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Testes de integração do {@see PathResolver}: resolve o conjunto completo de
 * artefatos de um domínio (Shared + todas as Features + tests/factory/migration)
 * e valida a coerência e a permanência de toda a árvore dentro dos bounds da
 * raiz de módulos configurada.
 */
final class PathResolverIntegrationTest extends TestCase
{
    private string $fakeAppPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeAppPath = sys_get_temp_dir().'/cqfeature-path-resolver-integration/app';
        @mkdir($this->fakeAppPath.'/Modules', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname($this->fakeAppPath));
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }
        rmdir($dir);
    }

    public function test_full_artifact_map_for_new_domain_is_consistent_and_within_bounds(): void
    {
        $config = CqFeatureConfig::fromArray([]);
        $resolver = new PathResolver($config, 'Financial', 'Finance', $this->fakeAppPath);

        $modulesRoot = $this->fakeAppPath.'/Modules/';
        $projectRoot = dirname($this->fakeAppPath);

        // Shared.
        $shared = $resolver->sharedFiles();
        $this->assertCount(6, $shared);

        // Todas as features CRUD.
        $features = ['create', 'delete', 'update', 'list', 'find'];
        $all = $resolver->allFeatureFiles($features);
        $this->assertCount(5, $all);

        // Coleta de TODOS os paths que devem viver sob app/Modules.
        $moduleScopedPaths = $shared;
        foreach ($all as $files) {
            $moduleScopedPaths = array_merge($moduleScopedPaths, array_values($files));
        }

        foreach ($moduleScopedPaths as $path) {
            $this->assertStringStartsWith($modulesRoot, $path);
            $this->assertStringNotContainsString('..', $path);
        }

        // Cada feature aponta para a sua própria pasta e os arquivos coincidem
        // com featurePath()/featureTestPath().
        foreach ($features as $feature) {
            $featureDir = $resolver->featurePath($feature);
            $this->assertStringStartsWith($modulesRoot, $featureDir);

            foreach ($resolver->featureFiles($feature) as $artifactPath) {
                $this->assertStringStartsWith($featureDir.'/', $artifactPath);
            }

            $this->assertStringStartsWith(
                $projectRoot.'/tests/Feature/Modules/Financial/Finance/Features/',
                $resolver->featureTestPath($feature)
            );
        }

        // Artefatos de projeto (fora de app/Modules, mas sob o projectRoot).
        $this->assertStringStartsWith($projectRoot.'/tests/Feature/Modules/', $resolver->testCasePath());
        $this->assertStringStartsWith($projectRoot.'/database/factories/Modules/', $resolver->factoryPath());
        $this->assertStringStartsWith($projectRoot.'/database/migrations/', $resolver->migrationPath());
        $this->assertSame('finances', $resolver->tableName());
    }

    public function test_full_artifact_map_for_aggregate_is_consistent(): void
    {
        $config = CqFeatureConfig::fromArray([]);
        $resolver = new PathResolver($config, 'Financial', 'FinanceHistory', $this->fakeAppPath, 'Finance');

        $aggregateRoot = $this->fakeAppPath.'/Modules/Financial/Finance/Aggregates/FinanceHistory';

        $this->assertSame($aggregateRoot, $resolver->domainPath());

        foreach ($resolver->sharedFiles() as $path) {
            $this->assertStringStartsWith($aggregateRoot.'/Shared/', $path);
        }

        foreach ($resolver->allFeatureFiles(['create', 'find']) as $files) {
            foreach ($files as $path) {
                $this->assertStringStartsWith($aggregateRoot.'/Features/', $path);
            }
        }

        $this->assertSame(
            'App\\Modules\\Financial\\Finance\\Aggregates\\FinanceHistory\\Shared',
            $resolver->sharedNamespace()
        );
        $this->assertSame(
            $this->fakeAppPath.'/Modules/Financial/Finance',
            $resolver->parentDomainPath()
        );
    }

    public function test_custom_config_changes_whole_tree_location_and_namespace(): void
    {
        $config = CqFeatureConfig::fromArray([
            'root_namespace' => 'Acme',
            'segments' => ['modules' => 'Domains'],
            'paths' => [
                'tests_base' => 'tests/Integration/Domains',
                'factories_base' => 'database/factories/Domains',
            ],
        ]);
        $resolver = new PathResolver($config, 'Sales', 'Order', $this->fakeAppPath);
        $projectRoot = dirname($this->fakeAppPath);

        $this->assertSame($this->fakeAppPath.'/Domains/Sales/Order', $resolver->domainPath());
        $this->assertSame('Acme\\Domains\\Sales\\Order', $resolver->baseNamespace());

        foreach ($resolver->sharedFiles() as $path) {
            $this->assertStringStartsWith($this->fakeAppPath.'/Domains/Sales/Order/Shared/', $path);
        }

        $this->assertStringStartsWith(
            $projectRoot.'/tests/Integration/Domains/Sales/Order/',
            $resolver->testCasePath()
        );
        $this->assertStringStartsWith(
            $projectRoot.'/database/factories/Domains/Sales/Order/',
            $resolver->factoryPath()
        );
    }
}
