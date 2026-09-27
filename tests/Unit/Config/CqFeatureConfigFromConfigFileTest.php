<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Cq\CqFeature\Config\CqFeatureConfig;

/**
 * Teste de integração: hidratação do VO a partir do arquivo de config real
 * (`config/cqfeature.php`), no mesmo formato que o Laravel entregaria via
 * `config('cqfeature')`.
 *
 * Garante que o arquivo publicável e os defaults do VO permanecem em paridade
 * (sem chaves faltantes/divergentes), travando a "regra de ouro" (RN-01).
 */
final class CqFeatureConfigFromConfigFileTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function loadConfigArray(): array
    {
        $path = dirname(__DIR__, 3).'/config/cqfeature.php';

        self::assertFileExists($path, 'O arquivo config/cqfeature.php deve existir e ser publicável.');

        /** @var array<string, mixed> $config */
        $config = require $path;

        self::assertIsArray($config);

        return $config;
    }

    public function test_carrega_arquivo_real_e_hidrata_vo_integro(): void
    {
        $array = $this->loadConfigArray();
        $config = CqFeatureConfig::fromArray($array);

        self::assertSame('App', $config->rootNamespace());
        self::assertSame('Modules', $config->modulesSegment());
        self::assertSame('Shared', $config->sharedSegment());
        self::assertSame('Features', $config->featuresSegment());
        self::assertSame('Aggregates', $config->aggregatesSegment());

        self::assertSame('app', $config->appPath());
        self::assertSame('tests/Feature/Modules', $config->testsBase());
        self::assertSame('database/factories/Modules', $config->factoriesBase());
        self::assertSame('database/migrations', $config->migrationsBase());

        self::assertSame('routes/api/modules', $config->routesBase());
        self::assertSame('module_kebab', $config->routePrefixStrategy());
        self::assertSame(['api', 'JWT', 'cors', 'localization'], $config->routeMiddleware());

        self::assertSame('feature', $config->commandName());
        self::assertNull($config->publishedStubsPath());
    }

    public function test_arquivo_real_e_defaults_do_vo_sao_identicos(): void
    {
        $fromFile = CqFeatureConfig::fromArray($this->loadConfigArray());
        $fromDefaults = CqFeatureConfig::fromArray([]);

        self::assertSame($fromDefaults->rootNamespace(), $fromFile->rootNamespace());
        self::assertSame($fromDefaults->modulesSegment(), $fromFile->modulesSegment());
        self::assertSame($fromDefaults->sharedSegment(), $fromFile->sharedSegment());
        self::assertSame($fromDefaults->featuresSegment(), $fromFile->featuresSegment());
        self::assertSame($fromDefaults->aggregatesSegment(), $fromFile->aggregatesSegment());
        self::assertSame($fromDefaults->appPath(), $fromFile->appPath());
        self::assertSame($fromDefaults->testsBase(), $fromFile->testsBase());
        self::assertSame($fromDefaults->factoriesBase(), $fromFile->factoriesBase());
        self::assertSame($fromDefaults->migrationsBase(), $fromFile->migrationsBase());
        self::assertSame($fromDefaults->sharedSubfolders(), $fromFile->sharedSubfolders());
        self::assertSame($fromDefaults->featureSubfolders(), $fromFile->featureSubfolders());
        self::assertSame($fromDefaults->routesBase(), $fromFile->routesBase());
        self::assertSame($fromDefaults->routePrefixStrategy(), $fromFile->routePrefixStrategy());
        self::assertSame($fromDefaults->routeMiddleware(), $fromFile->routeMiddleware());
        self::assertSame($fromDefaults->routeFeatureMap(), $fromFile->routeFeatureMap());
        self::assertSame($fromDefaults->commandName(), $fromFile->commandName());

        // package_path: o arquivo usa __DIR__.'/../stubs' (config/ → raiz) e o
        // default do VO usa raiz-do-pacote/stubs; ambos resolvem ao mesmo dir.
        self::assertSame(
            realpath($fromDefaults->packageStubsPath()) ?: $fromDefaults->packageStubsPath(),
            realpath($fromFile->packageStubsPath()) ?: $fromFile->packageStubsPath(),
        );
    }

    public function test_arquivo_real_cobre_todas_as_chaves_de_topo(): void
    {
        $array = $this->loadConfigArray();

        foreach ([
            'root_namespace',
            'segments',
            'paths',
            'shared_subfolders',
            'feature_subfolders',
            'routes',
            'command',
            'stubs',
        ] as $key) {
            self::assertArrayHasKey($key, $array, "config/cqfeature.php deve declarar a chave '{$key}'.");
        }
    }
}
