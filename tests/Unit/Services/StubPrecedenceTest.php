<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Cq\CqFeature\Config\CqFeatureConfig;
use Cq\CqFeature\Services\StubRenderer;

/**
 * Testes de integração da precedência de stubs (RN-02).
 *
 * O stub publicado no projeto (`published_path`) vence o stub interno do pacote
 * (`package_path`); quando o stub solicitado não existe no diretório publicado,
 * a resolução recai no diretório do pacote.
 */
final class StubPrecedenceTest extends TestCase
{
    private string $publishedDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publishedDir = sys_get_temp_dir().'/cqfeature_published_stubs_'.uniqid();
        mkdir($this->publishedDir.'/Shared', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->publishedDir);
        parent::tearDown();
    }

    public function test_published_stub_wins_over_package_stub(): void
    {
        // Stub publicado alterado para o mesmo nome resolvido do pacote.
        file_put_contents(
            $this->publishedDir.'/Shared/Entity.php.stub',
            "<?php\n\nnamespace {{RootNamespace}}\\Modules\\{{Module}}\\{{Domain}}\\Shared\\Entities;\n\n// PUBLISHED OVERRIDE\nfinal class {{Domain}}Entity {}\n"
        );

        $renderer = new StubRenderer(CqFeatureConfig::fromArray([
            'stubs' => ['published_path' => $this->publishedDir],
        ]));

        $content = $renderer->render('Shared.Entity', [
            'Module' => 'Financial',
            'Domain' => 'Finance',
        ]);

        $this->assertStringContainsString('// PUBLISHED OVERRIDE', $content);
        $this->assertStringContainsString('App\\Modules\\Financial\\Finance\\Shared\\Entities', $content);
        // A versão do pacote tem `extends BaseEntity`; a publicada não.
        $this->assertStringNotContainsString('extends BaseEntity', $content);
    }

    public function test_falls_back_to_package_stub_when_not_published(): void
    {
        // Diretório publicado existe mas NÃO contém o stub solicitado.
        $renderer = new StubRenderer(CqFeatureConfig::fromArray([
            'stubs' => ['published_path' => $this->publishedDir],
        ]));

        $content = $renderer->render('Shared.Model', [
            'Module' => 'Financial',
            'Domain' => 'Finance',
        ]);

        // Conteúdo veio do stub do pacote.
        $this->assertStringContainsString('class Finance extends ModelBase', $content);
        $this->assertStringContainsString('App\\Modules\\Financial\\Finance\\Shared\\Models', $content);
        $this->assertFalse(str_contains($content, '{{'));
    }

    public function test_explicit_stub_dirs_define_precedence_order(): void
    {
        $highPriority = sys_get_temp_dir().'/cqfeature_high_'.uniqid();
        $lowPriority = sys_get_temp_dir().'/cqfeature_low_'.uniqid();
        mkdir($highPriority, 0777, true);
        mkdir($lowPriority, 0777, true);
        file_put_contents($highPriority.'/Thing.php.stub', 'HIGH');
        file_put_contents($lowPriority.'/Thing.php.stub', 'LOW');

        $renderer = new StubRenderer(
            CqFeatureConfig::fromArray([]),
            [$highPriority, $lowPriority],
        );

        try {
            $this->assertSame('HIGH', $renderer->render('Thing', []));
        } finally {
            unlink($highPriority.'/Thing.php.stub');
            unlink($lowPriority.'/Thing.php.stub');
            rmdir($highPriority);
            rmdir($lowPriority);
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
