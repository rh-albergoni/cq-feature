<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Cq\CqFeature\Config\CqFeatureConfig;
use Cq\CqFeature\Services\StubRenderer;
use RuntimeException;

/**
 * Testes unitários do {@see StubRenderer} com {@see CqFeatureConfig} injetado.
 *
 * Cobrem: render default idêntico ao FeatureMaker do `cq/api` (namespace
 * começa com `App` — RN-01), injeção do `{{RootNamespace}}` da config
 * (override RNF-03), rejeição de placeholders residuais, fallback de extensão
 * `.php.stub` → `.md.stub` e erro de stub inexistente.
 */
final class StubRendererTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $config
     */
    private function renderer(array $config = []): StubRenderer
    {
        return new StubRenderer(CqFeatureConfig::fromArray($config));
    }

    // ─── Render default (RN-01) ──────────────────────────────────────

    public function test_substitutes_module_and_domain_placeholders(): void
    {
        $content = $this->renderer()->render('Shared.Entity', [
            'Module' => 'Financial',
            'Domain' => 'Finance',
        ]);

        $this->assertStringContainsString('Financial', $content);
        $this->assertStringContainsString('Finance', $content);
        $this->assertStringNotContainsString('{{Module}}', $content);
        $this->assertStringNotContainsString('{{Domain}}', $content);
    }

    public function test_default_namespace_starts_with_app(): void
    {
        $content = $this->renderer()->render('Shared.Entity', [
            'Module' => 'Financial',
            'Domain' => 'Finance',
        ]);

        $this->assertStringContainsString(
            'App\\Modules\\Financial\\Finance\\Shared\\Entities',
            $content
        );
        $this->assertStringContainsString('FinanceEntity', $content);
    }

    public function test_root_namespace_is_injected_without_explicit_replace(): void
    {
        // Mesmo sem passar 'RootNamespace' no replace, o placeholder é
        // preenchido a partir da config (default 'App').
        $content = $this->renderer()->render('Shared.Model', [
            'Module' => 'Financial',
            'Domain' => 'Finance',
        ]);

        $this->assertStringContainsString('class Finance extends ModelBase', $content);
        $this->assertStringContainsString(
            'App\\Modules\\Financial\\Finance\\Shared\\Models',
            $content
        );
        $this->assertFalse(str_contains($content, '{{'));
    }

    // ─── RootNamespace customizado (RNF-03) ──────────────────────────

    public function test_custom_root_namespace_is_applied(): void
    {
        $content = $this->renderer(['root_namespace' => 'Acme'])->render('Shared.Entity', [
            'Module' => 'Financial',
            'Domain' => 'Finance',
        ]);

        $this->assertStringContainsString(
            'Acme\\Modules\\Financial\\Finance\\Shared\\Entities',
            $content
        );
        $this->assertStringContainsString('Acme\\Shared\\Entities\\BaseEntity', $content);
        $this->assertStringNotContainsString('App\\Modules', $content);
        $this->assertFalse(str_contains($content, '{{'));
    }

    public function test_explicit_root_namespace_in_replace_overrides_config_default(): void
    {
        // Quando o chamador passa RootNamespace explicitamente, ele prevalece.
        $content = $this->renderer()->render('Shared.Entity', [
            'RootNamespace' => 'Override',
            'Module' => 'Financial',
            'Domain' => 'Finance',
        ]);

        $this->assertStringContainsString('Override\\Modules\\Financial\\Finance', $content);
    }

    // ─── Placeholder residual ────────────────────────────────────────

    public function test_throws_on_residual_placeholder_when_key_missing(): void
    {
        // QueryRepository usa {{table_name}}; sem ele, sobra placeholder residual.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Placeholder residual '{{table_name}}'");

        $this->renderer()->render('Shared.Repositories.QueryRepository', [
            'Module' => 'Financial',
            'Domain' => 'Finance',
        ]);
    }

    public function test_throws_on_residual_placeholder_in_temp_stub(): void
    {
        $tempDir = sys_get_temp_dir().'/cqfeature_stub_renderer_'.uniqid();
        mkdir($tempDir, 0777, true);
        file_put_contents($tempDir.'/Test.php.stub', '<?php class {{Domain}}{{Extra}} {}');

        $renderer = new StubRenderer(CqFeatureConfig::fromArray([]), [$tempDir]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Placeholder residual '{{Extra}}'");

        try {
            $renderer->render('Test', ['Domain' => 'Finance']);
        } finally {
            unlink($tempDir.'/Test.php.stub');
            rmdir($tempDir);
        }
    }

    // ─── Fallback de extensão .php.stub → .md.stub ───────────────────

    public function test_falls_back_to_md_stub_when_php_stub_absent(): void
    {
        // Features.Readme existe apenas como .md.stub no pacote.
        $content = $this->renderer()->render('Features.Readme', [
            'Module' => 'Financial',
            'Domain' => 'Finance',
            'Action' => 'Create',
            'module_kebab' => 'financial',
            'domain_plural_kebab' => 'finances',
            'action_kebab' => 'create',
        ]);

        $this->assertStringContainsString('# FinanceCreate', $content);
        $this->assertFalse(str_contains($content, '{{'));
    }

    public function test_php_stub_takes_precedence_over_md_stub_in_same_dir(): void
    {
        $tempDir = sys_get_temp_dir().'/cqfeature_stub_ext_'.uniqid();
        mkdir($tempDir, 0777, true);
        file_put_contents($tempDir.'/Thing.php.stub', 'PHP_VERSION');
        file_put_contents($tempDir.'/Thing.md.stub', 'MD_VERSION');

        $renderer = new StubRenderer(CqFeatureConfig::fromArray([]), [$tempDir]);

        try {
            $this->assertSame('PHP_VERSION', $renderer->render('Thing', []));
        } finally {
            unlink($tempDir.'/Thing.php.stub');
            unlink($tempDir.'/Thing.md.stub');
            rmdir($tempDir);
        }
    }

    // ─── Stub inexistente ────────────────────────────────────────────

    public function test_throws_for_nonexistent_stub(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Stub 'Shared.NonExistent' não encontrado");

        $this->renderer()->render('Shared.NonExistent', [
            'Module' => 'Financial',
            'Domain' => 'Finance',
        ]);
    }

    public function test_renders_with_different_module_and_domain(): void
    {
        $content = $this->renderer()->render('Shared.Entity', [
            'Module' => 'HumanResources',
            'Domain' => 'Employee',
        ]);

        $this->assertStringContainsString(
            'App\\Modules\\HumanResources\\Employee\\Shared\\Entities',
            $content
        );
        $this->assertStringContainsString('EmployeeEntity', $content);
        $this->assertFalse(str_contains($content, '{{'));
    }
}
