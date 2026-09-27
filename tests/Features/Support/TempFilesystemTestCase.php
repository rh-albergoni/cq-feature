<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Features\Support;

use PHPUnit\Framework\TestCase;
use Cq\CqFeature\Config\CqFeatureConfig;
use Cq\CqFeature\Services\PathResolver;
use Cq\CqFeature\Services\RouteRegistry;
use Cq\CqFeature\Services\ScaffoldService;
use Cq\CqFeature\Services\StubRenderer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

/**
 * Base de teste E2E para a suíte `Features`.
 *
 * Cria um filesystem temporário isolado em {@see sys_get_temp_dir()} no
 * {@see setUp()} e o remove integralmente no {@see tearDown()} (RNF-02 — nunca
 * toca o app real). Expõe helpers para:
 * - instanciar {@see CqFeatureConfig}/{@see PathResolver}/{@see ScaffoldService}
 *   com a config default ou sobrescrita;
 * - listar e comparar a árvore de arquivos gerada ({@see assertTreeEquals()},
 *   {@see listTree()});
 * - remoção recursiva de diretórios ({@see removeRecursive()}).
 *
 * PHPUnit puro — sem Orchestra Testbench.
 */
abstract class TempFilesystemTestCase extends TestCase
{
    /** Raiz temporária (base_path simulado do app consumidor). */
    protected string $tmp;

    /** Caminho de `app/` dentro da raiz temporária. */
    protected string $appPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmp = sys_get_temp_dir().'/cqf_'.uniqid('', true);
        $this->appPath = $this->tmp.'/app';

        mkdir($this->appPath.'/Modules', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeRecursive($this->tmp);

        parent::tearDown();
    }

    /**
     * Cria uma config. Por padrão usa os defaults (RN-01); aceita override
     * parcial idêntico ao formato de `config/cqfeature.php`.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function makeConfig(array $overrides = []): CqFeatureConfig
    {
        return CqFeatureConfig::fromArray($overrides);
    }

    /**
     * Cria um {@see PathResolver} apontando para o filesystem temporário.
     */
    protected function makePathResolver(
        string $module,
        string $domain,
        ?string $aggregateOf = null,
        ?CqFeatureConfig $config = null,
        ?string $appPath = null,
    ): PathResolver {
        return new PathResolver(
            $config ?? $this->makeConfig(),
            $module,
            $domain,
            $appPath ?? $this->appPath,
            $aggregateOf,
        );
    }

    /**
     * Cria um {@see ScaffoldService} pronto para gerar no filesystem temporário.
     */
    protected function makeService(
        PathResolver $paths,
        ?CqFeatureConfig $config = null,
        bool $registerRoutes = false,
    ): ScaffoldService {
        $config ??= $this->makeConfig();

        return new ScaffoldService(
            $paths,
            new StubRenderer($config),
            $registerRoutes ? new RouteRegistry($config, $this->tmp) : null,
        );
    }

    /**
     * Lista, de forma ordenada, todos os arquivos sob $root como caminhos
     * relativos a $root (com separador "/").
     *
     * @return array<int, string>
     */
    protected function listTree(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $files = [];

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                continue;
            }

            $relative = ltrim(substr($item->getPathname(), strlen($root)), '/\\');
            $files[] = str_replace('\\', '/', $relative);
        }

        sort($files);

        return $files;
    }

    /**
     * Garante que a árvore de arquivos sob $root é exatamente $expected
     * (conjunto de caminhos relativos), com diff claro em caso de divergência.
     *
     * @param  array<int, string>  $expected  Caminhos relativos esperados
     */
    protected function assertTreeEquals(array $expected, string $root): void
    {
        $expected = array_values(array_unique($expected));
        sort($expected);

        $actual = $this->listTree($root);

        $missing = array_values(array_diff($expected, $actual));
        $unexpected = array_values(array_diff($actual, $expected));

        $message = '';
        if ($missing !== []) {
            $message .= "Arquivos esperados e ausentes:\n  - ".implode("\n  - ", $missing)."\n";
        }
        if ($unexpected !== []) {
            $message .= "Arquivos presentes e não esperados:\n  + ".implode("\n  + ", $unexpected)."\n";
        }

        $this->assertSame($expected, $actual, $message);
    }

    /**
     * Remove recursivamente um diretório (e todo o seu conteúdo). No-op se o
     * diretório não existir.
     */
    protected function removeRecursive(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
