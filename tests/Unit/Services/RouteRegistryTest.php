<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Cq\CqFeature\Config\CqFeatureConfig;
use Cq\CqFeature\Services\RouteRegistry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Testes unitários do {@see RouteRegistry} com {@see CqFeatureConfig} injetado.
 *
 * Cobrem: resolução de path default, mapa de features (CRUD) idêntico ao
 * FeatureMaker do `cq/api` (RN-01), middleware/base/prefixo customizados via
 * config (RF-04), features customizadas (PascalCase) e features desconhecidas
 * silenciosamente ignoradas. ZERO literal de path/middleware/map (RNF-03).
 */
final class RouteRegistryTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/cqfeature-route-registry-unit-'.uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function registry(array $config = []): RouteRegistry
    {
        return new RouteRegistry(CqFeatureConfig::fromArray($config), $this->tempDir);
    }

    // ─── Cenário 1: Resolução de path default ────────────────────────

    public function test_resolve_route_path_uses_configured_base(): void
    {
        $registry = $this->registry();

        $path = $registry->resolveRoutePath('Financial', 'Finance');

        $this->assertStringEndsWith('routes/api/modules/financial/finances.php', $path);
        $this->assertStringStartsWith($this->tempDir, $path);
    }

    public function test_route_path_uses_kebab_for_module(): void
    {
        $registry = $this->registry();

        $path = $registry->resolveRoutePath('FinancialManagement', 'Payment');

        $this->assertStringContainsString('/financial-management/payments.php', $path);
    }

    public function test_register_new_file_creates_route_file(): void
    {
        $registry = $this->registry();

        $result = $registry->register('Financial', 'Finance', ['create', 'list']);

        $this->assertFileExists($result['path']);
        $this->assertStringEndsWith('routes/api/modules/financial/finances.php', $result['path']);
    }

    // ─── Cenário 2: Middleware e prefixo customizados ────────────────

    public function test_default_middleware_and_prefix(): void
    {
        $registry = $this->registry();

        $registry->register('Financial', 'Finance', ['create']);
        $content = $this->readRoutes($registry, 'Financial', 'Finance');

        $this->assertStringContainsString("'middleware' => ['api', 'JWT', 'cors', 'localization']", $content);
        $this->assertStringContainsString("'prefix' => 'financial'", $content);
    }

    public function test_custom_middleware_and_base_are_reflected_in_header(): void
    {
        $registry = $this->registry([
            'routes' => [
                'base' => 'routes/custom',
                'middleware' => ['web', 'auth'],
            ],
        ]);

        $result = $registry->register('Financial', 'Finance', ['create']);
        $content = file_get_contents($result['path']);

        $this->assertStringContainsString('routes/custom/financial/finances.php', $result['path']);
        $this->assertStringContainsString("'middleware' => ['web', 'auth']", $content);
    }

    public function test_empty_middleware_renders_empty_array(): void
    {
        $registry = $this->registry(['routes' => ['middleware' => []]]);

        $registry->register('Financial', 'Finance', ['create']);
        $content = $this->readRoutes($registry, 'Financial', 'Finance');

        $this->assertStringContainsString("'middleware' => []", $content);
    }

    public function test_domain_kebab_prefix_strategy(): void
    {
        $registry = $this->registry(['routes' => ['prefix' => 'domain_kebab']]);

        $registry->register('Financial', 'Finance', ['create']);
        $content = $this->readRoutes($registry, 'Financial', 'Finance');

        $this->assertStringContainsString("'prefix' => 'finance'", $content);
    }

    // ─── Cenário 3: Mapa de features (CRUD) ──────────────────────────

    public function test_create_generates_post_route(): void
    {
        $registry = $this->registry();
        $registry->register('Financial', 'Finance', ['create']);
        $content = $this->readRoutes($registry, 'Financial', 'Finance');

        $this->assertStringContainsString("Route::post('finances'", $content);
        $this->assertStringContainsString('FinanceCreateController::class', $content);
    }

    public function test_list_generates_get_route(): void
    {
        $registry = $this->registry();
        $registry->register('Financial', 'Finance', ['list']);
        $content = $this->readRoutes($registry, 'Financial', 'Finance');

        $this->assertStringContainsString("Route::get('finances'", $content);
        $this->assertStringContainsString('FinanceListController::class', $content);
    }

    public function test_find_generates_get_id_route(): void
    {
        $registry = $this->registry();
        $registry->register('Financial', 'Finance', ['find']);
        $content = $this->readRoutes($registry, 'Financial', 'Finance');

        $this->assertStringContainsString("Route::get('finances/{id}'", $content);
        $this->assertStringContainsString('FinanceFindController::class', $content);
    }

    public function test_update_generates_put_id_route(): void
    {
        $registry = $this->registry();
        $registry->register('Financial', 'Finance', ['update']);
        $content = $this->readRoutes($registry, 'Financial', 'Finance');

        $this->assertStringContainsString("Route::put('finances/{id}'", $content);
        $this->assertStringContainsString('FinanceUpdateController::class', $content);
    }

    public function test_delete_generates_delete_id_route(): void
    {
        $registry = $this->registry();
        $registry->register('Financial', 'Finance', ['delete']);
        $content = $this->readRoutes($registry, 'Financial', 'Finance');

        $this->assertStringContainsString("Route::delete('finances/{id}'", $content);
        $this->assertStringContainsString('FinanceDeleteController::class', $content);
    }

    public function test_full_controller_use_statement_and_facade_import(): void
    {
        $registry = $this->registry();
        $registry->register('Financial', 'Finance', ['create', 'list']);
        $content = $this->readRoutes($registry, 'Financial', 'Finance');

        $this->assertStringContainsString(
            'use App\Modules\Financial\Finance\Features\FinanceCreate\Controllers\FinanceCreateController;',
            $content,
        );
        $this->assertStringContainsString(
            'use App\Modules\Financial\Finance\Features\FinanceList\Controllers\FinanceListController;',
            $content,
        );
        $this->assertStringContainsString('use Illuminate\Support\Facades\Route;', $content);
    }

    public function test_comment_with_prefix_and_slug(): void
    {
        $registry = $this->registry();
        $registry->register('Financial', 'Finance', ['create']);
        $content = $this->readRoutes($registry, 'Financial', 'Finance');

        $this->assertStringContainsString('// financial/finances', $content);
    }

    public function test_controller_class_reference_in_route_line(): void
    {
        $registry = $this->registry();
        $registry->register('Financial', 'Finance', ['create']);
        $content = $this->readRoutes($registry, 'Financial', 'Finance');

        $this->assertStringContainsString("[FinanceCreateController::class, 'execute']", $content);
    }

    public function test_result_reports_added_routes(): void
    {
        $registry = $this->registry();

        $result = $registry->register('Financial', 'Finance', ['create', 'list']);

        $this->assertCount(2, $result['added']);
        $this->assertEmpty($result['skipped']);
    }

    public function test_register_only_requested_features(): void
    {
        $registry = $this->registry();
        $registry->register('Financial', 'Finance', ['create', 'list']);
        $content = $this->readRoutes($registry, 'Financial', 'Finance');

        $this->assertStringContainsString("Route::post('finances'", $content);
        $this->assertStringContainsString("Route::get('finances'", $content);
        $this->assertStringNotContainsString('Route::put(', $content);
        $this->assertStringNotContainsString('Route::delete(', $content);
    }

    public function test_custom_feature_map_overrides_default(): void
    {
        $registry = $this->registry([
            'routes' => [
                'feature_map' => [
                    'archive' => ['method' => 'patch', 'suffix' => '/{id}/archive'],
                ],
            ],
        ]);

        $registry->register('Financial', 'Finance', ['archive']);
        $content = $this->readRoutes($registry, 'Financial', 'Finance');

        $this->assertStringContainsString("Route::patch('finances/{id}/archive'", $content);
        $this->assertStringContainsString('FinanceArchiveController::class', $content);
    }

    // ─── Cenário 4: Feature customizada (PascalCase) ─────────────────

    public function test_custom_feature_registers_post_kebab_route(): void
    {
        $registry = $this->registry();

        $result = $registry->register('Financial', 'Finance', ['AgroupInstalments']);
        $content = $this->readRoutes($registry, 'Financial', 'Finance');

        $this->assertCount(1, $result['added']);
        $this->assertStringContainsString("Route::post('finances/agroup-instalments'", $content);
        $this->assertStringContainsString('FinanceAgroupInstalmentsController', $content);
    }

    public function test_unknown_features_are_silently_ignored(): void
    {
        $registry = $this->registry();

        $result = $registry->register('Financial', 'Finance', ['create', 'nonexistent']);
        $content = $this->readRoutes($registry, 'Financial', 'Finance');

        $this->assertCount(1, $result['added']);
        $this->assertStringContainsString("Route::post('finances'", $content);
        $this->assertStringNotContainsString('nonexistent', $content);
    }

    public function test_custom_root_namespace_in_use_statement(): void
    {
        $registry = $this->registry([
            'root_namespace' => 'Acme',
            'segments' => ['modules' => 'Domains'],
        ]);

        $registry->register('Financial', 'Finance', ['create']);
        $content = $this->readRoutes($registry, 'Financial', 'Finance');

        $this->assertStringContainsString(
            'use Acme\Domains\Financial\Finance\Features\FinanceCreate\Controllers\FinanceCreateController;',
            $content,
        );
    }

    // ─── Cenário 5: Arquivo gerado é PHP válido ──────────────────────

    public function test_generated_file_is_valid_php(): void
    {
        $registry = $this->registry();
        $registry->register('Financial', 'Finance', ['create', 'list', 'find', 'update', 'delete']);
        $path = $registry->resolveRoutePath('Financial', 'Finance');

        $output = [];
        $exitCode = 0;
        exec('php -l '.escapeshellarg($path).' 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, 'Generated route file should be valid PHP: '.implode("\n", $output));
    }

    private function readRoutes(RouteRegistry $registry, string $module, string $domain): string
    {
        $content = file_get_contents($registry->resolveRoutePath($module, $domain));
        $this->assertNotFalse($content);

        return $content;
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
