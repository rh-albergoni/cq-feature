<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Cq\CqFeature\Config\CqFeatureConfig;
use Cq\CqFeature\Services\RouteRegistry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Testes de integração do {@see RouteRegistry} contra um filesystem temporário.
 *
 * Cobrem: criação de arquivo novo com diretórios inexistentes e o append
 * idempotente por método+URI — registrar duas vezes as mesmas features não
 * duplica rotas nem altera o arquivo (RF-04).
 */
final class RouteRegistryIntegrationTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/cqfeature-route-registry-int-'.uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function registry(): RouteRegistry
    {
        return new RouteRegistry(CqFeatureConfig::fromArray([]), $this->tempDir);
    }

    // ─── Criação de arquivo novo ─────────────────────────────────────

    public function test_creates_directory_structure_and_header_when_missing(): void
    {
        $registry = $this->registry();

        $result = $registry->register('NewModule', 'NewDomain', ['create']);

        $this->assertFileExists($result['path']);
        $this->assertDirectoryExists(dirname($result['path']));

        $content = (string) file_get_contents($result['path']);
        $this->assertStringContainsString('Route::group([', $content);
        $this->assertStringContainsString("'middleware' => ['api', 'JWT', 'cors', 'localization']", $content);
        $this->assertStringContainsString("'prefix' => 'new-module'", $content);
    }

    // ─── Append idempotente ──────────────────────────────────────────

    public function test_second_registration_with_same_features_does_not_duplicate(): void
    {
        $registry = $this->registry();
        $features = ['create', 'list', 'find', 'update', 'delete'];

        $registry->register('Financial', 'Finance', $features);
        $path = $registry->resolveRoutePath('Financial', 'Finance');
        $before = (string) file_get_contents($path);

        $result = $registry->register('Financial', 'Finance', $features);
        $after = (string) file_get_contents($path);

        $this->assertSame($before, $after, 'Second register() must not alter the file');
        $this->assertEmpty($result['added']);
        $this->assertCount(5, $result['skipped']);

        $this->assertSame(1, substr_count($after, "Route::post('finances'"));
        $this->assertSame(1, substr_count($after, "Route::get('finances'"));
        $this->assertSame(1, substr_count($after, "Route::get('finances/{id}'"));
        $this->assertSame(1, substr_count($after, "Route::put('finances/{id}'"));
        $this->assertSame(1, substr_count($after, "Route::delete('finances/{id}'"));
    }

    public function test_appends_new_features_to_existing_file(): void
    {
        $registry = $this->registry();

        $registry->register('Financial', 'Finance', ['create']);
        $result = $registry->register('Financial', 'Finance', ['list', 'find']);

        $path = $registry->resolveRoutePath('Financial', 'Finance');
        $content = (string) file_get_contents($path);

        $this->assertCount(2, $result['added']);
        $this->assertStringContainsString("Route::post('finances'", $content);
        $this->assertStringContainsString("Route::get('finances'", $content);
        $this->assertStringContainsString("Route::get('finances/{id}'", $content);
        $this->assertStringContainsString('FinanceCreateController;', $content);
        $this->assertStringContainsString('FinanceListController;', $content);
    }

    public function test_file_remains_valid_php_after_append(): void
    {
        $registry = $this->registry();

        $registry->register('Financial', 'Finance', ['create']);
        $registry->register('Financial', 'Finance', ['list', 'find']);

        $path = $registry->resolveRoutePath('Financial', 'Finance');

        $output = [];
        $exitCode = 0;
        exec('php -l '.escapeshellarg($path).' 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, 'Route file after append should be valid PHP: '.implode("\n", $output));
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
