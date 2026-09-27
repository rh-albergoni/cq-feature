<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Features;

use Cq\CqFeature\Config\CqFeatureConfig;
use Cq\CqFeature\Services\ScaffoldConfig;
use Cq\CqFeature\Tests\Features\Support\TempFilesystemTestCase;

/**
 * E2E de override de configuração (RF-03, RNF-04).
 *
 * Prova que alterar `config/cqfeature.php` muda o LOCAL e o NAMESPACE do código
 * gerado — sem editar o pacote. Cobre root_namespace, segmento de módulos,
 * subpastas de Shared, base de testes/factories e middleware de rotas. É o
 * contraponto ao teste de equivalência: o que com defaults é idêntico ao
 * FeatureMaker, aqui muda de forma observável quando a config muda.
 */
final class ConfigOverrideTest extends TempFilesystemTestCase
{
    private const MODULE = 'Financial';

    private const DOMAIN = 'Finance';

    public function test_root_namespace_override_changes_generated_namespace(): void
    {
        $config = $this->makeConfig(['root_namespace' => 'Acme']);
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN, config: $config);

        $this->makeService($resolver, $config)->run(
            ScaffoldConfig::make(module: self::MODULE, domain: self::DOMAIN, features: ['create']),
        );

        $content = (string) file_get_contents($resolver->featureFiles('create')['Controller']);
        $this->assertStringContainsString('namespace Acme\\Modules\\Financial\\Finance\\Features', $content);
        $this->assertStringNotContainsString('namespace App\\Modules', $content);
        $this->assertSame(
            'Acme\\Modules\\Financial\\Finance\\Features\\FinanceCreate',
            $resolver->featureNamespace('create'),
        );
    }

    public function test_modules_segment_override_changes_filesystem_path(): void
    {
        // O segmento de módulos governa o LOCAL no filesystem (PathResolver,
        // config-driven). O segmento dentro do namespace renderizado vem do
        // stub (literal `Modules`, 1:1 com o FeatureMaker), customizável apenas
        // republicando os stubs (RN-02) — por isso aqui validamos o path e o
        // namespace resolvido pelo código (não o conteúdo do stub).
        $config = $this->makeConfig(['segments' => ['modules' => 'Domains']]);
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN, config: $config);

        $this->makeService($resolver, $config)->run(
            ScaffoldConfig::make(module: self::MODULE, domain: self::DOMAIN, features: ['create']),
        );

        $this->assertDirectoryExists($this->appPath.'/Domains/Financial/Finance');
        $this->assertDirectoryDoesNotExist($this->appPath.'/Modules/Financial/Finance');

        // baseNamespace() é derivado da config (fonte única, zero hardcode).
        $this->assertSame('App\\Domains\\Financial\\Finance', $resolver->baseNamespace());
    }

    public function test_tests_base_override_changes_test_location(): void
    {
        $config = $this->makeConfig(['paths' => ['tests_base' => 'tests/Custom/Modules']]);
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN, config: $config);

        $this->makeService($resolver, $config)->run(
            ScaffoldConfig::make(module: self::MODULE, domain: self::DOMAIN, features: ['create']),
        );

        $expected = $this->tmp.'/tests/Custom/Modules/Financial/Finance/Shared/FinanceTestCase.php';
        $this->assertSame($expected, $resolver->testCasePath());
        $this->assertFileExists($expected);
        $this->assertDirectoryDoesNotExist($this->tmp.'/tests/Feature/Modules');
    }

    public function test_factories_base_override_changes_factory_location(): void
    {
        $config = $this->makeConfig(['paths' => ['factories_base' => 'database/factories/Custom']]);
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN, config: $config);

        $this->makeService($resolver, $config)->run(
            ScaffoldConfig::make(module: self::MODULE, domain: self::DOMAIN, features: ['create']),
        );

        $expected = $this->tmp.'/database/factories/Custom/Financial/Finance/Shared/Models/FinanceFactory.php';
        $this->assertSame($expected, $resolver->factoryPath());
        $this->assertFileExists($expected);
    }

    public function test_shared_subfolder_override_changes_artifact_location(): void
    {
        $config = $this->makeConfig(['shared_subfolders' => ['Entity' => 'DomainEntities']]);
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN, config: $config);

        $this->makeService($resolver, $config)->run(
            ScaffoldConfig::make(module: self::MODULE, domain: self::DOMAIN, features: ['create']),
        );

        $this->assertFileExists($this->appPath.'/Modules/Financial/Finance/Shared/DomainEntities/FinanceEntity.php');
        $this->assertDirectoryDoesNotExist($this->appPath.'/Modules/Financial/Finance/Shared/Entities');
    }

    public function test_route_overrides_change_base_and_middleware(): void
    {
        $config = $this->makeConfig([
            'routes' => [
                'base' => 'routes/v2/modules',
                'middleware' => ['api', 'custom-guard'],
            ],
        ]);
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN, config: $config);

        $this->makeService($resolver, $config, registerRoutes: true)->run(
            ScaffoldConfig::make(
                module: self::MODULE,
                domain: self::DOMAIN,
                features: ['create'],
                registerRoutes: true,
            ),
        );

        $routePath = $this->tmp.'/routes/v2/modules/financial/finances.php';
        $this->assertFileExists($routePath);
        $this->assertFileDoesNotExist($this->tmp.'/routes/api/modules/financial/finances.php');

        $content = (string) file_get_contents($routePath);
        $this->assertStringContainsString("'custom-guard'", $content);
        $this->assertStringNotContainsString('JWT', $content);
    }

    public function test_aggregates_segment_override_changes_path_and_rendered_namespace(): void
    {
        // O segmento de agregados flui pelo placeholder {{Module}} (valor
        // computado), então a override propaga tanto para o path quanto para o
        // namespace renderizado — diferente dos segmentos baked-in dos stubs.
        $config = $this->makeConfig(['segments' => ['aggregates' => 'Aggs']]);
        $resolver = $this->makePathResolver('Financial', 'FinanceHistory', 'Finance', $config);

        $this->makeService($resolver, $config)->run(
            ScaffoldConfig::make(
                module: 'Financial',
                domain: 'FinanceHistory',
                features: ['create'],
                aggregateOf: 'Finance',
            ),
        );

        $this->assertDirectoryExists($this->appPath.'/Modules/Financial/Finance/Aggs/FinanceHistory');

        $content = (string) file_get_contents($resolver->featureFiles('create')['Controller']);
        $this->assertStringContainsString(
            'namespace App\\Modules\\Financial\\Finance\\Aggs\\FinanceHistory\\Features\\FinanceHistoryCreate\\Controllers;',
            $content,
        );
        $this->assertStringNotContainsString('\\Aggregates\\', $content);
    }

    public function test_combined_overrides_match_task_example(): void
    {
        // Cenário do 8_task.md: root_namespace=Acme + tests_base alternativo.
        $config = CqFeatureConfig::fromArray([
            'root_namespace' => 'Acme',
            'paths' => ['tests_base' => 'tests/Custom/Modules'],
        ]);
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN, config: $config);

        $this->makeService($resolver, $config)->run(
            ScaffoldConfig::make(module: self::MODULE, domain: self::DOMAIN, features: ['create']),
        );

        $this->assertStringStartsWith('Acme\\Modules\\', $resolver->baseNamespace());
        $this->assertStringContainsString('/tests/Custom/Modules/', $resolver->testCasePath());
        $this->assertFileExists($resolver->testCasePath());
    }
}
