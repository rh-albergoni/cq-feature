<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Cq\CqFeature\Config\CqFeatureConfig;
use Cq\CqFeature\Services\StubRenderer;

/**
 * Render dos stubs de Feature (CRUD + Custom) com o {@see StubRenderer}.
 *
 * Valida namespaces, ausência de placeholders residuais e validade sintática
 * do PHP gerado para todas as features, reproduzindo o FeatureMaker (RN-01).
 */
final class StubRendererFeaturesTest extends TestCase
{
    private StubRenderer $renderer;

    private string $tempDir;

    private const CREATE_STUBS = [
        'Features.Create.Controller' => ['subDir' => 'Controllers', 'fileName' => 'FinanceCreateController.php'],
        'Features.Create.Service' => ['subDir' => 'Services', 'fileName' => 'FinanceCreateService.php'],
        'Features.Create.Request' => ['subDir' => 'Requests', 'fileName' => 'FinanceCreateRequest.php'],
        'Features.Create.Dto' => ['subDir' => 'Dtos', 'fileName' => 'FinanceCreateDto.php'],
        'Features.Create.CommandRepository' => ['subDir' => 'Repositories/Commands', 'fileName' => 'FinanceCreateCommandRepository.php'],
    ];

    private const DELETE_STUBS = [
        'Features.Delete.Controller' => ['subDir' => 'Controllers', 'fileName' => 'FinanceDeleteController.php'],
        'Features.Delete.Service' => ['subDir' => 'Services', 'fileName' => 'FinanceDeleteService.php'],
        'Features.Delete.CommandRepository' => ['subDir' => 'Repositories/Commands', 'fileName' => 'FinanceDeleteCommandRepository.php'],
    ];

    private const UPDATE_STUBS = [
        'Features.Update.Controller' => ['subDir' => 'Controllers', 'fileName' => 'FinanceUpdateController.php'],
        'Features.Update.Service' => ['subDir' => 'Services', 'fileName' => 'FinanceUpdateService.php'],
        'Features.Update.Request' => ['subDir' => 'Requests', 'fileName' => 'FinanceUpdateRequest.php'],
        'Features.Update.Dto' => ['subDir' => 'Dtos', 'fileName' => 'FinanceUpdateDto.php'],
        'Features.Update.CommandRepository' => ['subDir' => 'Repositories/Commands', 'fileName' => 'FinanceUpdateCommandRepository.php'],
    ];

    private const LIST_STUBS = [
        'Features.List.Controller' => ['subDir' => 'Controllers', 'fileName' => 'FinanceListController.php'],
        'Features.List.Service' => ['subDir' => 'Services', 'fileName' => 'FinanceListService.php'],
        'Features.List.Request' => ['subDir' => 'Requests', 'fileName' => 'FinanceListRequest.php'],
        'Features.List.FilterDto' => ['subDir' => 'FilterDtos', 'fileName' => 'FinanceListFilterDto.php'],
        'Features.List.Dto' => ['subDir' => 'Dtos', 'fileName' => 'FinanceListDto.php'],
        'Features.List.QueryDao' => ['subDir' => 'Dao/Queries', 'fileName' => 'FinanceListQueryDao.php'],
    ];

    private const FIND_STUBS = [
        'Features.Find.Controller' => ['subDir' => 'Controllers', 'fileName' => 'FinanceFindController.php'],
        'Features.Find.Service' => ['subDir' => 'Services', 'fileName' => 'FinanceFindService.php'],
        'Features.Find.ViewDto' => ['subDir' => 'Dtos', 'fileName' => 'FinanceViewDto.php'],
        'Features.Find.QueryDao' => ['subDir' => 'Dao/Queries', 'fileName' => 'FinanceFindQueryDao.php'],
        'Features.Find.QueryRepository' => ['subDir' => 'Repositories/Queries', 'fileName' => 'FinanceFindQueryRepository.php'],
    ];

    private const CUSTOM_STUBS = [
        'Features.Custom.Controller' => ['subDir' => 'Controllers', 'fileName' => 'FinanceArchiveController.php'],
        'Features.Custom.Service' => ['subDir' => 'Services', 'fileName' => 'FinanceArchiveService.php'],
        'Features.Custom.Request' => ['subDir' => 'Requests', 'fileName' => 'FinanceArchiveRequest.php'],
        'Features.Custom.Dto' => ['subDir' => 'Dtos', 'fileName' => 'FinanceArchiveDto.php'],
        'Features.Custom.CommandRepository' => ['subDir' => 'Repositories/Commands', 'fileName' => 'FinanceArchiveCommandRepository.php'],
        'Features.Custom.QueryRepository' => ['subDir' => 'Repositories/Queries', 'fileName' => 'FinanceArchiveQueryRepository.php'],
        'Features.Custom.CommandDao' => ['subDir' => 'Dao/Commands', 'fileName' => 'FinanceArchiveCommandDao.php'],
        'Features.Custom.QueryDao' => ['subDir' => 'Dao/Queries', 'fileName' => 'FinanceArchiveQueryDao.php'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new StubRenderer(CqFeatureConfig::fromArray([]));
        $this->tempDir = sys_get_temp_dir().'/cqfeature_features_render_'.uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    /**
     * @return array<string, string>
     */
    private function replace(string $action): array
    {
        return [
            'Module' => 'Financial',
            'Domain' => 'Finance',
            'Action' => $action,
            'domain_camel' => 'finance',
            'domain_snake' => 'finance',
            'domain_snake_plural' => 'finances',
            'table_name' => 'finances',
            'module_kebab' => 'financial',
            'moduleKebab' => 'financial',
            'domain_plural_kebab' => 'finances',
            'domainPluralKebab' => 'finances',
            'action_kebab' => strtolower($action),
            'action_snake' => strtolower($action),
        ];
    }

    /**
     * @return array<string, array{string, array<string, array{subDir: string, fileName: string}>}>
     */
    public static function featureProvider(): array
    {
        return [
            'create' => ['Create', self::CREATE_STUBS],
            'delete' => ['Delete', self::DELETE_STUBS],
            'update' => ['Update', self::UPDATE_STUBS],
            'list' => ['List', self::LIST_STUBS],
            'find' => ['Find', self::FIND_STUBS],
            'custom' => ['Archive', self::CUSTOM_STUBS],
        ];
    }

    /**
     * @param  array<string, array{subDir: string, fileName: string}>  $stubs
     *
     * @dataProvider featureProvider
     */
    public function test_feature_stubs_have_no_residual_placeholders(string $action, array $stubs): void
    {
        $replace = $this->replace($action);

        foreach ($stubs as $stubName => $meta) {
            $content = $this->renderer->render($stubName, $replace);

            $this->assertFalse(
                str_contains($content, '{{'),
                "Stub '{$stubName}' (feature {$action}) contém placeholders residuais"
            );
        }
    }

    /**
     * @param  array<string, array{subDir: string, fileName: string}>  $stubs
     *
     * @dataProvider featureProvider
     */
    public function test_feature_stubs_contain_default_root_namespace(string $action, array $stubs): void
    {
        $replace = $this->replace($action);

        foreach ($stubs as $stubName => $meta) {
            $content = $this->renderer->render($stubName, $replace);

            $this->assertStringContainsString(
                'App\\Modules\\Financial\\Finance\\Features\\Finance'.$action,
                $content,
                "Stub '{$stubName}' não contém o namespace base esperado"
            );
        }
    }

    /**
     * @param  array<string, array{subDir: string, fileName: string}>  $stubs
     *
     * @dataProvider featureProvider
     */
    public function test_feature_stubs_render_to_valid_php(string $action, array $stubs): void
    {
        $replace = $this->replace($action);
        $featureDir = 'Finance'.$action;

        foreach ($stubs as $stubName => $meta) {
            $content = $this->renderer->render($stubName, $replace);
            $dirPath = $this->tempDir.'/'.$featureDir.'/'.$meta['subDir'];

            if (! is_dir($dirPath)) {
                mkdir($dirPath, 0777, true);
            }

            $filePath = $dirPath.'/'.$meta['fileName'];
            file_put_contents($filePath, $content);

            $output = [];
            $exitCode = 0;
            exec("php -l {$filePath} 2>&1", $output, $exitCode);

            $this->assertSame(
                0,
                $exitCode,
                "Stub '{$stubName}' gerou PHP inválido: ".implode("\n", $output)
            );
        }
    }

    public function test_custom_root_namespace_applies_to_features(): void
    {
        $renderer = new StubRenderer(CqFeatureConfig::fromArray(['root_namespace' => 'Acme']));
        $replace = $this->replace('Create');

        foreach (self::CREATE_STUBS as $stubName => $meta) {
            $content = $renderer->render($stubName, $replace);

            $this->assertStringContainsString('Acme\\Modules\\Financial\\Finance', $content);
            $this->assertStringNotContainsString('App\\Modules', $content);
            $this->assertFalse(str_contains($content, '{{'));
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }

        rmdir($dir);
    }
}
