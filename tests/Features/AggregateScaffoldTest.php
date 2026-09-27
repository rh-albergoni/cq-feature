<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Features;

use Cq\CqFeature\Services\PathResolver;
use Cq\CqFeature\Services\ScaffoldConfig;
use Cq\CqFeature\Services\ScaffoldResult;
use Cq\CqFeature\Tests\Features\Support\TempFilesystemTestCase;

/**
 * E2E: scaffold de domínio agregado (`--aggregate-of`).
 *
 * Porta os cenários de geração do `FeatureMakerAggregateTest` para a suíte
 * `Features`: a estrutura (Shared + Features + Tests + Factory + Migration) é
 * criada DENTRO de `{Parent}/Aggregates/{Domain}` e os namespaces refletem o
 * agregado, sem tocar o domínio pai. Com a config default a saída é idêntica à
 * do FeatureMaker (RN-01).
 */
final class AggregateScaffoldTest extends TempFilesystemTestCase
{
    private const MODULE = 'Financial';

    private const DOMAIN = 'FinanceHistory';

    private const PARENT = 'Finance';

    public function test_creates_shared_inside_aggregates(): void
    {
        $this->runAggregateCrud();

        $domainPath = $this->appPath.'/Modules/Financial/Finance/Aggregates/FinanceHistory';
        $this->assertDirectoryExists($domainPath.'/Shared/Entities');
        $this->assertDirectoryExists($domainPath.'/Shared/Models');
        $this->assertDirectoryExists($domainPath.'/Shared/Dao/Commands');
        $this->assertDirectoryExists($domainPath.'/Shared/Dao/Queries');
        $this->assertDirectoryExists($domainPath.'/Shared/Repositories/Commands');
        $this->assertDirectoryExists($domainPath.'/Shared/Repositories/Queries');
    }

    public function test_creates_features_inside_aggregates(): void
    {
        $this->runAggregateCrud();

        $domainPath = $this->appPath.'/Modules/Financial/Finance/Aggregates/FinanceHistory';
        foreach (['Create', 'Delete', 'Update', 'List', 'Find'] as $action) {
            $this->assertDirectoryExists(
                $domainPath.'/Features/FinanceHistory'.$action,
                "Feature FinanceHistory{$action} deveria existir dentro de Aggregates",
            );
        }
    }

    public function test_creates_all_shared_and_feature_files(): void
    {
        $this->runAggregateCrud();

        $resolver = $this->resolver();
        foreach ($resolver->sharedFiles() as $identifier => $path) {
            $this->assertFileExists($path, "Shared [{$identifier}] não criado em {$path}");
        }
        foreach (['create', 'delete', 'update', 'list', 'find'] as $feature) {
            foreach ($resolver->featureFiles($feature) as $identifier => $path) {
                $this->assertFileExists($path, "Feature [{$feature}] arquivo [{$identifier}] não criado em {$path}");
            }
        }
    }

    public function test_entity_has_aggregate_namespace(): void
    {
        $this->runAggregateCrud();

        $content = (string) file_get_contents($this->resolver()->sharedFiles()['Entity']);
        $this->assertStringContainsString(
            'namespace App\\Modules\\Financial\\Finance\\Aggregates\\FinanceHistory\\Shared\\Entities;',
            $content,
        );
        $this->assertStringContainsString('class FinanceHistoryEntity', $content);
    }

    public function test_controller_has_aggregate_namespace(): void
    {
        $this->runAggregate(['create']);

        $content = (string) file_get_contents($this->resolver()->featureFiles('create')['Controller']);
        $this->assertStringContainsString(
            'namespace App\\Modules\\Financial\\Finance\\Aggregates\\FinanceHistory\\Features\\FinanceHistoryCreate\\Controllers;',
            $content,
        );
        $this->assertStringContainsString('class FinanceHistoryCreateController', $content);
    }

    public function test_no_residual_placeholders_and_valid_php(): void
    {
        $result = $this->runAggregateCrud();

        foreach ($result->getCreated() as $path) {
            $content = (string) file_get_contents($path);
            $this->assertStringNotContainsString('{{', $content, "Arquivo {$path} contém placeholders residuais");

            if (! str_ends_with($path, '.php')) {
                continue;
            }
            $output = [];
            $exitCode = 0;
            exec('php -l '.escapeshellarg($path).' 2>&1', $output, $exitCode);
            $this->assertSame(0, $exitCode, "Arquivo {$path} com erro de sintaxe: ".implode("\n", $output));
        }
    }

    public function test_does_not_create_files_in_parent_domain(): void
    {
        $this->runAggregateCrud();

        $this->assertDirectoryDoesNotExist($this->appPath.'/Modules/Financial/Finance/Features');
        $this->assertDirectoryDoesNotExist($this->appPath.'/Modules/Financial/Finance/Shared');
    }

    public function test_custom_feature_inside_aggregate(): void
    {
        $this->runAggregate(['FindLastAfter']);

        $resolver = $this->resolver();
        $featurePath = $resolver->featurePath('FindLastAfter');

        $this->assertFileExists($featurePath.'/Controllers/FinanceHistoryFindLastAfterController.php');
        $this->assertFileExists($featurePath.'/Services/FinanceHistoryFindLastAfterService.php');

        $content = (string) file_get_contents($featurePath.'/Controllers/FinanceHistoryFindLastAfterController.php');
        $this->assertStringContainsString(
            'App\\Modules\\Financial\\Finance\\Aggregates\\FinanceHistory\\Features\\FinanceHistoryFindLastAfter\\Controllers',
            $content,
        );
    }

    public function test_second_run_skips_existing_aggregate_files(): void
    {
        $resolver = $this->resolver();
        $service = $this->makeService($resolver);

        $first = $service->run($this->aggregateConfig(['create']));
        $this->assertTrue($first->isNewDomain());
        $this->assertGreaterThan(0, $first->createdCount());

        $second = $service->run($this->aggregateConfig(['create']));
        $this->assertFalse($second->isNewDomain());
        $this->assertSame(0, $second->createdCount());

        foreach ($resolver->featureFiles('create') as $path) {
            $this->assertContains($path, $second->getSkipped());
        }
    }

    private function resolver(): PathResolver
    {
        return $this->makePathResolver(self::MODULE, self::DOMAIN, self::PARENT);
    }

    /**
     * @param  array<int, string>  $features
     */
    private function aggregateConfig(array $features): ScaffoldConfig
    {
        return ScaffoldConfig::make(
            module: self::MODULE,
            domain: self::DOMAIN,
            features: $features,
            aggregateOf: self::PARENT,
        );
    }

    /**
     * @param  array<int, string>  $features
     */
    private function runAggregate(array $features): ScaffoldResult
    {
        $result = $this->makeService($this->resolver())->run($this->aggregateConfig($features));
        $this->assertFalse($result->hasErrors(), implode("\n", $result->getErrors()));

        return $result;
    }

    private function runAggregateCrud(): ScaffoldResult
    {
        return $this->runAggregate(['crud']);
    }
}
