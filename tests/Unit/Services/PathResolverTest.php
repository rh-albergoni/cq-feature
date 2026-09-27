<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Unit\Services;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Cq\CqFeature\Config\CqFeatureConfig;
use Cq\CqFeature\Services\PathResolver;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Testes unitários do {@see PathResolver} com {@see CqFeatureConfig} injetado.
 *
 * Cobrem: paths/namespaces default idênticos ao FeatureMaker do `cq/api`
 * (RN-01), overrides de root_namespace e de bases de filesystem, resolução de
 * agregados (aggregateOf) e bloqueio de path traversal com a raiz de módulos
 * configurável (RNF-02/RNF-03).
 */
final class PathResolverTest extends TestCase
{
    private string $fakeAppPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeAppPath = sys_get_temp_dir().'/cqfeature-path-resolver-test/app';
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

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolver(
        string $module = 'Financial',
        string $domain = 'Finance',
        ?string $aggregateOf = null,
        array $config = [],
    ): PathResolver {
        return new PathResolver(
            CqFeatureConfig::fromArray($config),
            $module,
            $domain,
            $this->fakeAppPath,
            $aggregateOf,
        );
    }

    // ─── Defaults: paths ─────────────────────────────────────────────

    public function test_domain_path_returns_correct_path(): void
    {
        $this->assertSame(
            $this->fakeAppPath.'/Modules/Financial/Finance',
            $this->resolver()->domainPath()
        );
    }

    public function test_domain_path_within_app_modules(): void
    {
        $path = $this->resolver()->domainPath();

        $this->assertStringContainsString('Modules/Financial/Finance', $path);
        $this->assertStringNotContainsString('..', $path);
    }

    public function test_shared_path_returns_correct_path(): void
    {
        $this->assertSame(
            $this->fakeAppPath.'/Modules/Financial/Finance/Shared',
            $this->resolver()->sharedPath()
        );
    }

    public function test_features_path_returns_correct_path(): void
    {
        $this->assertSame(
            $this->fakeAppPath.'/Modules/Financial/Finance/Features',
            $this->resolver()->featuresPath()
        );
    }

    public function test_shared_files_returns_all_shared_files(): void
    {
        $files = $this->resolver()->sharedFiles();

        $this->assertArrayHasKey('Entity', $files);
        $this->assertArrayHasKey('Model', $files);
        $this->assertArrayHasKey('CommandDao', $files);
        $this->assertArrayHasKey('QueryDao', $files);
        $this->assertArrayHasKey('CommandRepository', $files);
        $this->assertArrayHasKey('QueryRepository', $files);
        $this->assertCount(6, $files);
    }

    public function test_shared_files_follow_finance_pattern(): void
    {
        $files = $this->resolver()->sharedFiles();
        $shared = $this->fakeAppPath.'/Modules/Financial/Finance/Shared';

        $this->assertSame($shared.'/Entities/FinanceEntity.php', $files['Entity']);
        $this->assertSame($shared.'/Models/Finance.php', $files['Model']);
        $this->assertSame($shared.'/Dao/Commands/FinanceCommandDao.php', $files['CommandDao']);
        $this->assertSame($shared.'/Dao/Queries/FinanceQueryDao.php', $files['QueryDao']);
        $this->assertSame($shared.'/Repositories/Commands/FinanceCommandRepository.php', $files['CommandRepository']);
        $this->assertSame($shared.'/Repositories/Queries/FinanceQueryRepository.php', $files['QueryRepository']);
    }

    public function test_shared_files_paths_within_app_modules(): void
    {
        foreach ($this->resolver()->sharedFiles() as $key => $path) {
            $this->assertStringStartsWith($this->fakeAppPath.'/Modules/', $path, "File {$key} not within app/Modules");
            $this->assertStringNotContainsString('..', $path, "File {$key} contains path traversal");
        }
    }

    // ─── Defaults: feature files ─────────────────────────────────────

    public function test_feature_path_for_create(): void
    {
        $this->assertSame(
            $this->fakeAppPath.'/Modules/Financial/Finance/Features/FinanceCreate',
            $this->resolver()->featurePath('create')
        );
    }

    public function test_feature_files_for_create(): void
    {
        $files = $this->resolver()->featureFiles('create');
        $base = $this->fakeAppPath.'/Modules/Financial/Finance/Features/FinanceCreate';

        $this->assertCount(6, $files);
        $this->assertSame($base.'/Controllers/FinanceCreateController.php', $files['Controller']);
        $this->assertSame($base.'/Services/FinanceCreateService.php', $files['Service']);
        $this->assertSame($base.'/Requests/FinanceCreateRequest.php', $files['Request']);
        $this->assertSame($base.'/Dtos/FinanceCreateDto.php', $files['Dto']);
        $this->assertSame($base.'/Repositories/Commands/FinanceCreateCommandRepository.php', $files['CommandRepository']);
        $this->assertSame($base.'/README.md', $files['Readme']);
    }

    public function test_feature_files_for_delete(): void
    {
        $files = $this->resolver()->featureFiles('delete');
        $base = $this->fakeAppPath.'/Modules/Financial/Finance/Features/FinanceDelete';

        $this->assertCount(4, $files);
        $this->assertSame($base.'/Controllers/FinanceDeleteController.php', $files['Controller']);
        $this->assertSame($base.'/Services/FinanceDeleteService.php', $files['Service']);
        $this->assertSame($base.'/Repositories/Commands/FinanceDeleteCommandRepository.php', $files['CommandRepository']);
        $this->assertSame($base.'/README.md', $files['Readme']);
    }

    public function test_feature_files_for_update(): void
    {
        $files = $this->resolver()->featureFiles('update');
        $base = $this->fakeAppPath.'/Modules/Financial/Finance/Features/FinanceUpdate';

        $this->assertCount(6, $files);
        $this->assertSame($base.'/Controllers/FinanceUpdateController.php', $files['Controller']);
        $this->assertSame($base.'/Services/FinanceUpdateService.php', $files['Service']);
        $this->assertSame($base.'/Requests/FinanceUpdateRequest.php', $files['Request']);
        $this->assertSame($base.'/Dtos/FinanceUpdateDto.php', $files['Dto']);
        $this->assertSame($base.'/Repositories/Commands/FinanceUpdateCommandRepository.php', $files['CommandRepository']);
        $this->assertSame($base.'/README.md', $files['Readme']);
    }

    public function test_feature_files_for_list(): void
    {
        $files = $this->resolver()->featureFiles('list');
        $base = $this->fakeAppPath.'/Modules/Financial/Finance/Features/FinanceList';

        $this->assertCount(7, $files);
        $this->assertSame($base.'/Controllers/FinanceListController.php', $files['Controller']);
        $this->assertSame($base.'/Services/FinanceListService.php', $files['Service']);
        $this->assertSame($base.'/Requests/FinanceListRequest.php', $files['Request']);
        $this->assertSame($base.'/FilterDtos/FinanceListFilterDto.php', $files['FilterDto']);
        $this->assertSame($base.'/Dtos/FinanceListDto.php', $files['Dto']);
        $this->assertSame($base.'/Dao/Queries/FinanceListQueryDao.php', $files['QueryDao']);
        $this->assertSame($base.'/README.md', $files['Readme']);
    }

    public function test_feature_files_for_find(): void
    {
        $files = $this->resolver()->featureFiles('find');
        $base = $this->fakeAppPath.'/Modules/Financial/Finance/Features/FinanceFind';

        $this->assertCount(6, $files);
        $this->assertSame($base.'/Controllers/FinanceFindController.php', $files['Controller']);
        $this->assertSame($base.'/Services/FinanceFindService.php', $files['Service']);
        $this->assertSame($base.'/Dtos/FinanceViewDto.php', $files['ViewDto']);
        $this->assertSame($base.'/Dao/Queries/FinanceFindQueryDao.php', $files['QueryDao']);
        $this->assertSame($base.'/Repositories/Queries/FinanceFindQueryRepository.php', $files['QueryRepository']);
        $this->assertSame($base.'/README.md', $files['Readme']);
    }

    public function test_feature_files_case_insensitive(): void
    {
        $resolver = $this->resolver();

        $this->assertSame($resolver->featureFiles('create'), $resolver->featureFiles('CREATE'));
        $this->assertSame($resolver->featureFiles('create'), $resolver->featureFiles('Create'));
    }

    public function test_all_feature_files_returns_all_features(): void
    {
        $all = $this->resolver()->allFeatureFiles(['create', 'delete', 'update', 'list', 'find']);

        $this->assertCount(5, $all);
        $this->assertArrayHasKey('create', $all);
        $this->assertArrayHasKey('delete', $all);
        $this->assertArrayHasKey('update', $all);
        $this->assertArrayHasKey('list', $all);
        $this->assertArrayHasKey('find', $all);
    }

    public function test_all_feature_files_paths_within_app_modules(): void
    {
        $all = $this->resolver()->allFeatureFiles(['create', 'delete', 'update', 'list', 'find']);

        foreach ($all as $feature => $files) {
            foreach ($files as $key => $path) {
                $this->assertStringStartsWith(
                    $this->fakeAppPath.'/Modules/',
                    $path,
                    "Feature {$feature}/{$key} not within app/Modules"
                );
                $this->assertStringNotContainsString('..', $path, "Feature {$feature}/{$key} contains path traversal");
            }
        }
    }

    public function test_feature_files_returns_custom_files_for_non_standard_feature(): void
    {
        $files = $this->resolver()->featureFiles('ChangeInstallmentStatus');
        $base = $this->fakeAppPath.'/Modules/Financial/Finance/Features/FinanceChangeInstallmentStatus';

        $this->assertCount(9, $files);
        $this->assertSame($base.'/Controllers/FinanceChangeInstallmentStatusController.php', $files['Controller']);
        $this->assertSame($base.'/Services/FinanceChangeInstallmentStatusService.php', $files['Service']);
        $this->assertSame($base.'/Requests/FinanceChangeInstallmentStatusRequest.php', $files['Request']);
        $this->assertSame($base.'/Dtos/FinanceChangeInstallmentStatusDto.php', $files['Dto']);
        $this->assertSame($base.'/Repositories/Commands/FinanceChangeInstallmentStatusCommandRepository.php', $files['CommandRepository']);
        $this->assertSame($base.'/Repositories/Queries/FinanceChangeInstallmentStatusQueryRepository.php', $files['QueryRepository']);
        $this->assertSame($base.'/Dao/Queries/FinanceChangeInstallmentStatusQueryDao.php', $files['QueryDao']);
        $this->assertSame($base.'/Dao/Commands/FinanceChangeInstallmentStatusCommandDao.php', $files['CommandDao']);
        $this->assertSame($base.'/README.md', $files['Readme']);
    }

    // ─── Defaults: namespaces ────────────────────────────────────────

    public function test_base_namespace(): void
    {
        $this->assertSame('App\\Modules\\Financial\\Finance', $this->resolver()->baseNamespace());
    }

    public function test_shared_namespace(): void
    {
        $this->assertSame('App\\Modules\\Financial\\Finance\\Shared', $this->resolver()->sharedNamespace());
    }

    public function test_feature_namespace(): void
    {
        $this->assertSame(
            'App\\Modules\\Financial\\Finance\\Features\\FinanceCreate',
            $this->resolver()->featureNamespace('create')
        );
    }

    public function test_base_namespace_with_submodule(): void
    {
        $this->assertSame(
            'App\\Modules\\Financial\\Sub\\Invoice',
            $this->resolver('Financial/Sub', 'Invoice')->baseNamespace()
        );
    }

    public function test_domain_path_with_different_module_and_domain(): void
    {
        $this->assertSame(
            $this->fakeAppPath.'/Modules/Sales/Order',
            $this->resolver('Sales', 'Order')->domainPath()
        );
    }

    public function test_shared_files_with_different_domain(): void
    {
        $files = $this->resolver('Sales', 'Order')->sharedFiles();
        $shared = $this->fakeAppPath.'/Modules/Sales/Order/Shared';

        $this->assertSame($shared.'/Entities/OrderEntity.php', $files['Entity']);
        $this->assertSame($shared.'/Models/Order.php', $files['Model']);
        $this->assertSame($shared.'/Dao/Commands/OrderCommandDao.php', $files['CommandDao']);
        $this->assertSame($shared.'/Dao/Queries/OrderQueryDao.php', $files['QueryDao']);
        $this->assertSame($shared.'/Repositories/Commands/OrderCommandRepository.php', $files['CommandRepository']);
        $this->assertSame($shared.'/Repositories/Queries/OrderQueryRepository.php', $files['QueryRepository']);
    }

    public function test_feature_files_with_different_domain(): void
    {
        $files = $this->resolver('Sales', 'Order')->featureFiles('create');
        $base = $this->fakeAppPath.'/Modules/Sales/Order/Features/OrderCreate';

        $this->assertSame($base.'/Controllers/OrderCreateController.php', $files['Controller']);
        $this->assertSame($base.'/Services/OrderCreateService.php', $files['Service']);
        $this->assertSame($base.'/Requests/OrderCreateRequest.php', $files['Request']);
        $this->assertSame($base.'/Dtos/OrderCreateDto.php', $files['Dto']);
        $this->assertSame($base.'/Repositories/Commands/OrderCreateCommandRepository.php', $files['CommandRepository']);
    }

    // ─── Defaults: tests / factory / migration / table ───────────────

    public function test_test_case_path(): void
    {
        $projectRoot = dirname($this->fakeAppPath);

        $this->assertSame(
            $projectRoot.'/tests/Feature/Modules/Financial/Finance/Shared/FinanceTestCase.php',
            $this->resolver()->testCasePath()
        );
    }

    public function test_feature_test_path_for_create(): void
    {
        $projectRoot = dirname($this->fakeAppPath);

        $this->assertSame(
            $projectRoot.'/tests/Feature/Modules/Financial/Finance/Features/FinanceCreate/FinanceCreateTest.php',
            $this->resolver()->featureTestPath('create')
        );
    }

    public function test_feature_test_path_for_custom_feature(): void
    {
        $projectRoot = dirname($this->fakeAppPath);

        $this->assertSame(
            $projectRoot.'/tests/Feature/Modules/Financial/Finance/Features/FinanceChangeInstallmentStatus/FinanceChangeInstallmentStatusTest.php',
            $this->resolver()->featureTestPath('ChangeInstallmentStatus')
        );
    }

    public function test_factory_path(): void
    {
        $projectRoot = dirname($this->fakeAppPath);

        $this->assertSame(
            $projectRoot.'/database/factories/Modules/Financial/Finance/Shared/Models/FinanceFactory.php',
            $this->resolver()->factoryPath()
        );
    }

    public function test_migration_path_format(): void
    {
        $path = $this->resolver()->migrationPath();

        $this->assertStringEndsWith('_create_finances_table.php', $path);
        $this->assertStringContainsString('/database/migrations/', $path);
    }

    public function test_table_name(): void
    {
        $this->assertSame('finances', $this->resolver()->tableName());
    }

    public function test_table_name_plural_complex_domain(): void
    {
        $this->assertSame('finance_e2_es', $this->resolver('Financial', 'FinanceE2E')->tableName());
    }

    // ─── Caso 2: override de root_namespace ──────────────────────────

    public function test_override_root_namespace_changes_all_namespaces(): void
    {
        $resolver = $this->resolver(config: ['root_namespace' => 'Acme']);

        $this->assertSame('Acme\\Modules\\Financial\\Finance', $resolver->baseNamespace());
        $this->assertSame('Acme\\Modules\\Financial\\Finance\\Shared', $resolver->sharedNamespace());
        $this->assertSame('Acme\\Modules\\Financial\\Finance\\Features\\FinanceCreate', $resolver->featureNamespace('create'));
    }

    public function test_override_segments_changes_paths_and_namespaces(): void
    {
        $resolver = $this->resolver(config: [
            'segments' => ['modules' => 'Domains', 'shared' => 'Common', 'features' => 'UseCases'],
        ]);

        $this->assertSame($this->fakeAppPath.'/Domains/Financial/Finance', $resolver->domainPath());
        $this->assertSame($this->fakeAppPath.'/Domains/Financial/Finance/Common', $resolver->sharedPath());
        $this->assertSame($this->fakeAppPath.'/Domains/Financial/Finance/UseCases', $resolver->featuresPath());
        $this->assertSame('App\\Domains\\Financial\\Finance\\Common', $resolver->sharedNamespace());
        $this->assertSame('App\\Domains\\Financial\\Finance\\UseCases\\FinanceCreate', $resolver->featureNamespace('create'));
    }

    // ─── Caso 3: override de bases de filesystem ─────────────────────

    public function test_override_filesystem_bases(): void
    {
        $projectRoot = dirname($this->fakeAppPath);
        $resolver = $this->resolver(config: [
            'paths' => [
                'tests_base' => 'tests/Integration/Modules',
                'factories_base' => 'database/factories/Domains',
                'migrations_base' => 'db/migrations',
            ],
        ]);

        $this->assertSame(
            $projectRoot.'/tests/Integration/Modules/Financial/Finance/Shared/FinanceTestCase.php',
            $resolver->testCasePath()
        );
        $this->assertSame(
            $projectRoot.'/tests/Integration/Modules/Financial/Finance/Features/FinanceCreate/FinanceCreateTest.php',
            $resolver->featureTestPath('create')
        );
        $this->assertSame(
            $projectRoot.'/database/factories/Domains/Financial/Finance/Shared/Models/FinanceFactory.php',
            $resolver->factoryPath()
        );
        $this->assertStringContainsString('/db/migrations/', $resolver->migrationPath());
    }

    public function test_override_subfolders_changes_file_layout(): void
    {
        $resolver = $this->resolver(config: [
            'shared_subfolders' => ['Entity' => 'DomainEntities', 'Model' => 'Eloquent'],
            'feature_subfolders' => ['Controller' => 'Http/Controllers'],
        ]);

        $shared = $this->fakeAppPath.'/Modules/Financial/Finance/Shared';
        $this->assertSame($shared.'/DomainEntities/FinanceEntity.php', $resolver->sharedFiles()['Entity']);
        $this->assertSame($shared.'/Eloquent/Finance.php', $resolver->sharedFiles()['Model']);

        $featureBase = $this->fakeAppPath.'/Modules/Financial/Finance/Features/FinanceCreate';
        $this->assertSame($featureBase.'/Http/Controllers/FinanceCreateController.php', $resolver->featureFiles('create')['Controller']);

        // Factory acompanha a subpasta de Model.
        $projectRoot = dirname($this->fakeAppPath);
        $this->assertSame(
            $projectRoot.'/database/factories/Modules/Financial/Finance/Shared/Eloquent/FinanceFactory.php',
            $resolver->factoryPath()
        );
    }

    // ─── Caso 4: agregados (aggregateOf) ─────────────────────────────

    public function test_aggregate_domain_path(): void
    {
        $resolver = $this->resolver('Financial', 'FinanceHistory', 'Finance');

        $this->assertSame(
            $this->fakeAppPath.'/Modules/Financial/Finance/Aggregates/FinanceHistory',
            $resolver->domainPath()
        );
        $this->assertSame(
            $this->fakeAppPath.'/Modules/Financial/Finance/Aggregates/FinanceHistory/Shared',
            $resolver->sharedPath()
        );
        $this->assertSame(
            $this->fakeAppPath.'/Modules/Financial/Finance/Aggregates/FinanceHistory/Features',
            $resolver->featuresPath()
        );
    }

    public function test_aggregate_namespaces(): void
    {
        $resolver = $this->resolver('Financial', 'FinanceHistory', 'Finance');

        $this->assertSame(
            'App\\Modules\\Financial\\Finance\\Aggregates\\FinanceHistory',
            $resolver->baseNamespace()
        );
        $this->assertSame(
            'App\\Modules\\Financial\\Finance\\Aggregates\\FinanceHistory\\Shared',
            $resolver->sharedNamespace()
        );
        $this->assertSame(
            'App\\Modules\\Financial\\Finance\\Aggregates\\FinanceHistory\\Features\\FinanceHistoryCreate',
            $resolver->featureNamespace('create')
        );
    }

    public function test_aggregate_tests_factory_and_parent_path(): void
    {
        $resolver = $this->resolver('Financial', 'FinanceHistory', 'Finance');
        $projectRoot = dirname($this->fakeAppPath);

        $this->assertSame(
            $projectRoot.'/tests/Feature/Modules/Financial/Finance/Aggregates/FinanceHistory/Shared/FinanceHistoryTestCase.php',
            $resolver->testCasePath()
        );
        $this->assertSame(
            $projectRoot.'/tests/Feature/Modules/Financial/Finance/Aggregates/FinanceHistory/Features/FinanceHistoryCreate/FinanceHistoryCreateTest.php',
            $resolver->featureTestPath('create')
        );
        $this->assertSame(
            $projectRoot.'/database/factories/Modules/Financial/Finance/Aggregates/FinanceHistory/Shared/Models/FinanceHistoryFactory.php',
            $resolver->factoryPath()
        );

        $this->assertSame('Finance', $resolver->aggregateOf());
        $this->assertSame(
            $this->fakeAppPath.'/Modules/Financial/Finance',
            $resolver->parentDomainPath()
        );
    }

    public function test_non_aggregate_has_null_parent_and_aggregate_of(): void
    {
        $resolver = $this->resolver();

        $this->assertNull($resolver->aggregateOf());
        $this->assertNull($resolver->parentDomainPath());
    }

    // ─── Caso 5: path traversal bloqueado ────────────────────────────

    public function test_path_traversal_in_module_is_blocked(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resolver('../Evil', 'Finance');
    }

    public function test_path_traversal_in_domain_is_blocked(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PathResolver(CqFeatureConfig::fromArray([]), '..', '..', $this->fakeAppPath);
    }

    public function test_bounds_respect_custom_modules_segment(): void
    {
        // Com a raiz de módulos customizada ('Domains'), o path permanece sob
        // essa raiz — sem exceção.
        $resolver = $this->resolver(config: ['segments' => ['modules' => 'Domains']]);

        $this->assertStringStartsWith($this->fakeAppPath.'/Domains/', $resolver->domainPath());
    }
}
