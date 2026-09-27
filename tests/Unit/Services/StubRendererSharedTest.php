<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Cq\CqFeature\Config\CqFeatureConfig;
use Cq\CqFeature\Services\StubRenderer;

/**
 * Render dos stubs Shared com o {@see StubRenderer} injetado pela config.
 *
 * Valida namespaces, nomes de classe, ausência de placeholders residuais e
 * validade sintática do PHP gerado (RN-01: idêntico ao FeatureMaker).
 */
final class StubRendererSharedTest extends TestCase
{
    private StubRenderer $renderer;

    private string $tempDir;

    private const SHARED_STUBS = [
        'Shared.Entity',
        'Shared.Model',
        'Shared.Dao.CommandDao',
        'Shared.Dao.QueryDao',
        'Shared.Repositories.CommandRepository',
        'Shared.Repositories.QueryRepository',
    ];

    private const EXPECTED_NAMESPACES = [
        'Shared.Entity' => 'App\\Modules\\Financial\\Finance\\Shared\\Entities',
        'Shared.Model' => 'App\\Modules\\Financial\\Finance\\Shared\\Models',
        'Shared.Dao.CommandDao' => 'App\\Modules\\Financial\\Finance\\Shared\\Dao\\Commands',
        'Shared.Dao.QueryDao' => 'App\\Modules\\Financial\\Finance\\Shared\\Dao\\Queries',
        'Shared.Repositories.CommandRepository' => 'App\\Modules\\Financial\\Finance\\Shared\\Repositories\\Commands',
        'Shared.Repositories.QueryRepository' => 'App\\Modules\\Financial\\Finance\\Shared\\Repositories\\Queries',
    ];

    private const EXPECTED_CLASSES = [
        'Shared.Entity' => 'FinanceEntity',
        'Shared.Model' => 'class Finance extends',
        'Shared.Dao.CommandDao' => 'FinanceCommandDao',
        'Shared.Dao.QueryDao' => 'FinanceQueryDao',
        'Shared.Repositories.CommandRepository' => 'FinanceCommandRepository',
        'Shared.Repositories.QueryRepository' => 'FinanceQueryRepository',
    ];

    private const EXPECTED_SUBDIRS = [
        'Shared.Entity' => 'Entities',
        'Shared.Model' => 'Models',
        'Shared.Dao.CommandDao' => 'Dao/Commands',
        'Shared.Dao.QueryDao' => 'Dao/Queries',
        'Shared.Repositories.CommandRepository' => 'Repositories/Commands',
        'Shared.Repositories.QueryRepository' => 'Repositories/Queries',
    ];

    private const EXPECTED_FILE_NAMES = [
        'Shared.Entity' => 'FinanceEntity.php',
        'Shared.Model' => 'Finance.php',
        'Shared.Dao.CommandDao' => 'FinanceCommandDao.php',
        'Shared.Dao.QueryDao' => 'FinanceQueryDao.php',
        'Shared.Repositories.CommandRepository' => 'FinanceCommandRepository.php',
        'Shared.Repositories.QueryRepository' => 'FinanceQueryRepository.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new StubRenderer(CqFeatureConfig::fromArray([]));
        $this->tempDir = sys_get_temp_dir().'/cqfeature_shared_render_'.uniqid();
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
    private function replace(): array
    {
        return [
            'Module' => 'Financial',
            'Domain' => 'Finance',
            'table_name' => 'finances',
        ];
    }

    public function test_all_shared_stubs_contain_correct_namespace(): void
    {
        foreach (self::SHARED_STUBS as $stubName) {
            $content = $this->renderer->render($stubName, $this->replace());

            $this->assertStringContainsString(
                self::EXPECTED_NAMESPACES[$stubName],
                $content,
                "Stub '{$stubName}' não contém o namespace esperado"
            );
        }
    }

    public function test_all_shared_stubs_contain_correct_class_name(): void
    {
        foreach (self::SHARED_STUBS as $stubName) {
            $content = $this->renderer->render($stubName, $this->replace());

            $this->assertStringContainsString(
                self::EXPECTED_CLASSES[$stubName],
                $content,
                "Stub '{$stubName}' não contém a classe esperada"
            );
        }
    }

    public function test_all_shared_stubs_have_no_residual_placeholders(): void
    {
        foreach (self::SHARED_STUBS as $stubName) {
            $content = $this->renderer->render($stubName, $this->replace());

            $this->assertFalse(
                str_contains($content, '{{'),
                "Stub '{$stubName}' contém placeholders residuais"
            );
        }
    }

    public function test_all_shared_stubs_have_valid_php_structure(): void
    {
        foreach (self::SHARED_STUBS as $stubName) {
            $content = $this->renderer->render($stubName, $this->replace());

            $this->assertStringContainsString('<?php', $content, "Stub '{$stubName}' sem tag PHP");
            $this->assertStringContainsString('namespace ', $content, "Stub '{$stubName}' sem namespace");
            $this->assertStringContainsString('class ', $content, "Stub '{$stubName}' sem classe");
        }
    }

    public function test_generated_files_are_syntactically_valid_php(): void
    {
        foreach (self::SHARED_STUBS as $stubName) {
            $content = $this->renderer->render($stubName, $this->replace());
            $dirPath = $this->tempDir.'/Shared/'.self::EXPECTED_SUBDIRS[$stubName];

            if (! is_dir($dirPath)) {
                mkdir($dirPath, 0777, true);
            }

            $filePath = $dirPath.'/'.self::EXPECTED_FILE_NAMES[$stubName];
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

    public function test_custom_root_namespace_applies_to_all_shared_stubs(): void
    {
        $renderer = new StubRenderer(CqFeatureConfig::fromArray(['root_namespace' => 'Acme']));

        foreach (self::SHARED_STUBS as $stubName) {
            $content = $renderer->render($stubName, $this->replace());

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
