<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Cq\CqFeature\Config\CqFeatureConfig;
use Cq\CqFeature\Services\PathResolver;
use Cq\CqFeature\Services\RouteRegistry;
use Cq\CqFeature\Services\ScaffoldConfig;
use Cq\CqFeature\Services\ScaffoldResult;
use Cq\CqFeature\Services\ScaffoldService;
use Cq\CqFeature\Services\StubRenderer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Testes de integração do {@see ScaffoldService} (ex-FeatureMakerService) com
 * filesystem temporário e {@see CqFeatureConfig} injetado nos colaboradores.
 *
 * Cobrem: plan()/run() em domínio novo (Shared + Factory + Migration + TestCase +
 * Features + Tests — RN-03), domínio existente (sem Shared), skip de arquivos
 * existentes (nunca sobrescreve), captura de erro por item com prosseguimento dos
 * demais, agregação (Aggregates), registro de rotas e o shape do {@see ScaffoldResult}.
 */
final class ScaffoldServiceTest extends TestCase
{
    private string $tempDir;

    private string $appPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/cqfeature_scaffold_service_'.uniqid();
        $this->appPath = $this->tempDir.'/app';
        mkdir($this->appPath.'/Modules', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function config(): CqFeatureConfig
    {
        return CqFeatureConfig::fromArray([]);
    }

    private function pathResolver(string $module, string $domain, ?string $aggregateOf = null): PathResolver
    {
        return new PathResolver($this->config(), $module, $domain, $this->appPath, $aggregateOf);
    }

    private function service(PathResolver $paths, ?StubRenderer $stubs = null, ?RouteRegistry $routes = null): ScaffoldService
    {
        return new ScaffoldService($paths, $stubs ?? new StubRenderer($this->config()), $routes);
    }

    // ── plan() ─────────────────────────────────────────────────────────

    public function test_plan_lists_shared_and_features_for_new_domain_without_writing(): void
    {
        $config = ScaffoldConfig::make(module: 'Financial', domain: 'FinanceTest', features: ['create']);
        $resolver = $this->pathResolver('Financial', 'FinanceTest');
        $plan = $this->service($resolver)->plan($config);

        $this->assertTrue($plan['isNewDomain']);

        foreach ($resolver->sharedFiles() as $path) {
            $this->assertContains($path, $plan['toCreate']);
        }
        foreach ($resolver->featureFiles('create') as $path) {
            $this->assertContains($path, $plan['toCreate']);
        }
        $this->assertContains($resolver->factoryPath(), $plan['toCreate']);
        $this->assertContains($resolver->testCasePath(), $plan['toCreate']);
        $this->assertContains($resolver->featureTestPath('create'), $plan['toCreate']);

        // plan() não escreve no disco.
        $this->assertDirectoryDoesNotExist($resolver->domainPath());
    }

    public function test_plan_detects_existing_domain_and_omits_shared(): void
    {
        mkdir($this->appPath.'/Modules/Financial/FinanceTest', 0777, true);

        $config = ScaffoldConfig::make(module: 'Financial', domain: 'FinanceTest', features: ['create']);
        $resolver = $this->pathResolver('Financial', 'FinanceTest');
        $plan = $this->service($resolver)->plan($config);

        $this->assertFalse($plan['isNewDomain']);
        foreach ($resolver->sharedFiles() as $path) {
            $this->assertNotContains($path, $plan['toCreate']);
        }
    }

    // ── run(): domínio novo ─────────────────────────────────────────────

    public function test_run_new_domain_creates_shared_factory_migration_testcase_features_and_tests(): void
    {
        $config = ScaffoldConfig::make(module: 'Financial', domain: 'FinanceTest', features: ['crud']);
        $resolver = $this->pathResolver('Financial', 'FinanceTest');

        $result = $this->service($resolver)->run($config);

        $this->assertInstanceOf(ScaffoldResult::class, $result);
        $this->assertTrue($result->isNewDomain());
        $this->assertFalse($result->hasErrors(), implode("\n", $result->getErrors()));
        $this->assertEmpty($result->getSkipped());

        foreach ($resolver->sharedFiles() as $path) {
            $this->assertFileExists($path);
            $this->assertContains($path, $result->getCreated());
        }

        foreach (['create', 'delete', 'update', 'list', 'find'] as $feature) {
            foreach ($resolver->featureFiles($feature) as $path) {
                $this->assertFileExists($path);
            }
            $this->assertFileExists($resolver->featureTestPath($feature));
        }

        $this->assertFileExists($resolver->factoryPath());
        $this->assertFileExists($resolver->testCasePath());

        // crud => migração criada.
        $migrations = array_filter(
            $result->getCreated(),
            static fn (string $p): bool => str_contains($p, '_create_finance_tests_table.php'),
        );
        $this->assertCount(1, $migrations);
    }

    public function test_run_without_crud_does_not_create_migration(): void
    {
        $config = ScaffoldConfig::make(module: 'Financial', domain: 'FinanceTest', features: ['create']);
        $resolver = $this->pathResolver('Financial', 'FinanceTest');

        $result = $this->service($resolver)->run($config);

        $migrations = array_filter(
            $result->getCreated(),
            static fn (string $p): bool => str_contains($p, '_create_finance_tests_table.php'),
        );
        $this->assertCount(0, $migrations);
    }

    public function test_run_renders_correct_namespaces_with_default_config(): void
    {
        $config = ScaffoldConfig::make(module: 'Financial', domain: 'FinanceTest', features: ['create']);
        $resolver = $this->pathResolver('Financial', 'FinanceTest');

        $this->service($resolver)->run($config);

        $entityContent = (string) file_get_contents($resolver->sharedFiles()['Entity']);
        $this->assertStringContainsString('App\\Modules\\Financial\\FinanceTest\\Shared\\Entities', $entityContent);
        $this->assertStringContainsString('FinanceTestEntity', $entityContent);

        $controllerContent = (string) file_get_contents($resolver->featureFiles('create')['Controller']);
        $this->assertStringContainsString(
            'App\\Modules\\Financial\\FinanceTest\\Features\\FinanceTestCreate\\Controllers',
            $controllerContent,
        );
        $this->assertStringNotContainsString('{{', $controllerContent);
    }

    // ── run(): domínio existente / skip ─────────────────────────────────

    public function test_run_existing_domain_skips_shared_and_creates_new_features(): void
    {
        // Primeiro run cria Shared + create.
        $first = ScaffoldConfig::make(module: 'Financial', domain: 'FinanceTest', features: ['create']);
        $resolver = $this->pathResolver('Financial', 'FinanceTest');
        $this->service($resolver)->run($first);

        // Segundo run adiciona 'list' a domínio existente.
        $second = ScaffoldConfig::make(module: 'Financial', domain: 'FinanceTest', features: ['list']);
        $result = $this->service($resolver)->run($second);

        $this->assertFalse($result->isNewDomain());

        // Shared não é recriado (não aparece em created).
        foreach ($resolver->sharedFiles() as $path) {
            $this->assertNotContains($path, $result->getCreated());
        }

        // Features de 'list' são criadas.
        foreach ($resolver->featureFiles('list') as $path) {
            $this->assertContains($path, $result->getCreated());
            $this->assertFileExists($path);
        }
    }

    public function test_existing_file_is_skipped_and_not_overwritten(): void
    {
        $resolver = $this->pathResolver('Financial', 'FinanceTest');
        $controllerPath = $resolver->featureFiles('create')['Controller'];
        mkdir(dirname($controllerPath), 0777, true);
        $original = '<?php // conteúdo original';
        file_put_contents($controllerPath, $original);

        $config = ScaffoldConfig::make(module: 'Financial', domain: 'FinanceTest', features: ['create']);
        $result = $this->service($resolver)->run($config);

        $this->assertContains($controllerPath, $result->getSkipped());
        $this->assertNotContains($controllerPath, $result->getCreated());
        $this->assertSame($original, file_get_contents($controllerPath));
    }

    public function test_second_run_skips_everything(): void
    {
        $config = ScaffoldConfig::make(module: 'Financial', domain: 'FinanceTest', features: ['create']);
        $resolver = $this->pathResolver('Financial', 'FinanceTest');
        $service = $this->service($resolver);

        $first = $service->run($config);
        $this->assertTrue($first->isNewDomain());
        $this->assertGreaterThan(0, $first->createdCount());

        $second = $service->run($config);
        $this->assertFalse($second->isNewDomain());
        $this->assertSame(0, $second->createdCount());
        $this->assertGreaterThan(0, $second->skippedCount());
    }

    // ── run(): erro por item ─────────────────────────────────────────────

    public function test_error_per_item_is_captured_and_other_items_proceed(): void
    {
        // Copia os stubs do pacote e remove um único stub para forçar erro
        // de render apenas naquele item (os demais prosseguem).
        $stubsCopy = $this->tempDir.'/stubs';
        $this->copyDirectory($this->config()->packageStubsPath(), $stubsCopy);
        unlink($stubsCopy.'/Features/Create/Service.php.stub');

        $resolver = $this->pathResolver('Financial', 'FinanceTest');
        $renderer = new StubRenderer($this->config(), [$stubsCopy]);
        $service = $this->service($resolver, $renderer);

        $config = ScaffoldConfig::make(module: 'Financial', domain: 'FinanceTest', features: ['create']);
        $result = $service->run($config);

        $this->assertTrue($result->hasErrors());
        $this->assertCount(1, $result->getErrors());
        $this->assertStringContainsString('FinanceTestCreateService.php', $result->getErrors()[0]);

        // Os demais artefatos da feature foram criados normalmente.
        $this->assertFileExists($resolver->featureFiles('create')['Controller']);
        $this->assertGreaterThan(0, $result->createdCount());
        $this->assertFileDoesNotExist($resolver->featureFiles('create')['Service']);
    }

    // ── run(): rotas ─────────────────────────────────────────────────────

    public function test_run_registers_routes_when_requested(): void
    {
        $config = ScaffoldConfig::make(
            module: 'Financial',
            domain: 'FinanceTest',
            features: ['create', 'list'],
            registerRoutes: true,
        );
        $resolver = $this->pathResolver('Financial', 'FinanceTest');
        $routes = new RouteRegistry($this->config(), $this->tempDir);
        $result = $this->service($resolver, null, $routes)->run($config);

        $this->assertFalse($result->hasErrors(), implode("\n", $result->getErrors()));

        $routeFile = $routes->resolveRoutePath('Financial', 'FinanceTest');
        $this->assertFileExists($routeFile);

        $routeContent = (string) file_get_contents($routeFile);
        $this->assertStringContainsString("Route::post('finance-tests'", $routeContent);
        $this->assertStringContainsString("Route::get('finance-tests'", $routeContent);
    }

    // ── run(): agregação ─────────────────────────────────────────────────

    public function test_run_aggregate_creates_structure_under_aggregates(): void
    {
        $config = ScaffoldConfig::make(
            module: 'Financial',
            domain: 'FinanceHistory',
            features: ['create'],
            aggregateOf: 'Finance',
        );
        $resolver = $this->pathResolver('Financial', 'FinanceHistory', 'Finance');
        $result = $this->service($resolver)->run($config);

        $this->assertFalse($result->hasErrors(), implode("\n", $result->getErrors()));

        $aggregateRoot = $this->appPath.'/Modules/Financial/Finance/Aggregates/FinanceHistory';
        $this->assertDirectoryExists($aggregateRoot.'/Shared');
        $this->assertDirectoryExists($aggregateRoot.'/Features/FinanceHistoryCreate');

        $controllerContent = (string) file_get_contents($resolver->featureFiles('create')['Controller']);
        $this->assertStringContainsString(
            'App\\Modules\\Financial\\Finance\\Aggregates\\FinanceHistory\\Features\\FinanceHistoryCreate\\Controllers',
            $controllerContent,
        );

        // Não escreve no domínio pai.
        $this->assertDirectoryDoesNotExist($this->appPath.'/Modules/Financial/Finance/Features');
    }

    // ── ScaffoldResult ────────────────────────────────────────────────────

    public function test_scaffold_result_aggregates_counts_and_errors(): void
    {
        $result = new ScaffoldResult(
            created: ['/a.php', '/b.php'],
            skipped: ['/c.php'],
            errors: ['boom'],
            isNewDomain: false,
        );

        $this->assertSame(['/a.php', '/b.php'], $result->getCreated());
        $this->assertSame(['/c.php'], $result->getSkipped());
        $this->assertSame(['boom'], $result->getErrors());
        $this->assertTrue($result->hasErrors());
        $this->assertSame(2, $result->createdCount());
        $this->assertSame(1, $result->skippedCount());
        $this->assertFalse($result->isNewDomain());
    }

    public function test_scaffold_result_defaults_without_errors(): void
    {
        $result = new ScaffoldResult(created: ['/a.php']);

        $this->assertFalse($result->hasErrors());
        $this->assertEmpty($result->getSkipped());
        $this->assertEmpty($result->getErrors());
        $this->assertTrue($result->isNewDomain());
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function copyDirectory(string $source, string $destination): void
    {
        mkdir($destination, 0777, true);

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($items as $item) {
            $target = $destination.'/'.$items->getSubPathName();
            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0777, true);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }

        rmdir($dir);
    }
}
