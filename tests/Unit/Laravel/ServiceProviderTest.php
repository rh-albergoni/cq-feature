<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Unit\Laravel;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\TestCase;
use Cq\CqFeature\Config\CqFeatureConfig;
use Cq\CqFeature\Console\FeatureCommand;
use Cq\CqFeature\Laravel\CqFeatureServiceProvider;
use Cq\CqFeature\Tests\Unit\Laravel\Fixtures\AppStub;

/**
 * Exercita o {@see CqFeatureServiceProvider} sob `Illuminate\Container\Container`
 * puro (sem Orchestra Testbench), cobrindo: merge de config + singleton do
 * {@see CqFeatureConfig}, command name dinâmico e as tags de publish
 * (`cqfeature-config` e `cqfeature-stubs`).
 */
final class ServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ServiceProvider::$publishGroups = [];
        ServiceProvider::$publishes = [];
    }

    protected function tearDown(): void
    {
        ServiceProvider::$publishGroups = [];
        ServiceProvider::$publishes = [];
        Container::setInstance(null);
        parent::tearDown();
    }

    private function makeApp(bool $runningInConsole = true): AppStub
    {
        $app = new AppStub($runningInConsole);
        $app->instance('config', new Repository());
        Container::setInstance($app);

        return $app;
    }

    // ── register() — merge de config + singleton ──────────────────────────

    public function test_register_merges_default_config(): void
    {
        $app = $this->makeApp();
        (new CqFeatureServiceProvider($app))->register();

        $config = $app->make('config');
        self::assertSame('App', $config->get('cqfeature.root_namespace'));
        self::assertSame('feature', $config->get('cqfeature.command.name'));
        self::assertSame('Modules', $config->get('cqfeature.segments.modules'));
    }

    public function test_register_binds_cqfeatureconfig_as_singleton(): void
    {
        $app = $this->makeApp();
        (new CqFeatureServiceProvider($app))->register();

        $first = $app->make(CqFeatureConfig::class);
        $second = $app->make(CqFeatureConfig::class);

        self::assertInstanceOf(CqFeatureConfig::class, $first);
        self::assertSame($first, $second);
    }

    public function test_resolved_config_reflects_merged_values(): void
    {
        $app = $this->makeApp();
        (new CqFeatureServiceProvider($app))->register();

        $resolved = $app->make(CqFeatureConfig::class);

        self::assertSame('App', $resolved->rootNamespace());
        self::assertSame('feature', $resolved->commandName());
        self::assertSame('Modules', $resolved->modulesSegment());
    }

    // ── Command name dinâmico ─────────────────────────────────────────────

    public function test_overridden_command_name_propagates_to_command(): void
    {
        $app = $this->makeApp();
        (new CqFeatureServiceProvider($app))->register();

        // Sobrescreve o nome do comando ANTES da primeira resolução do singleton.
        $app->make('config')->set('cqfeature.command.name', 'cq:scaffold');

        $resolved = $app->make(CqFeatureConfig::class);
        self::assertSame('cq:scaffold', $resolved->commandName());

        $command = new FeatureCommand($resolved);
        self::assertSame('cq:scaffold', $command->getName());
    }

    // ── boot() — publishes (apenas em console) ────────────────────────────

    public function test_boot_registers_config_publish_tag(): void
    {
        $app = $this->makeApp(true);
        (new CqFeatureServiceProvider($app))->boot();

        $paths = ServiceProvider::pathsToPublish(CqFeatureServiceProvider::class, CqFeatureServiceProvider::CONFIG_TAG);

        self::assertNotEmpty($paths);
        self::assertTrue(
            $this->publishContainsSource($paths, CqFeatureServiceProvider::CONFIG_PATH),
            'Publish source da config deve apontar para o arquivo de config do pacote.',
        );
    }

    public function test_boot_registers_stubs_publish_tag(): void
    {
        $app = $this->makeApp(true);
        (new CqFeatureServiceProvider($app))->boot();

        $paths = ServiceProvider::pathsToPublish(CqFeatureServiceProvider::class, CqFeatureServiceProvider::STUBS_TAG);

        self::assertNotEmpty($paths);
        self::assertTrue(
            $this->publishContainsSource($paths, CqFeatureServiceProvider::STUBS_PATH),
            'Publish source dos stubs deve apontar para o diretório de stubs do pacote.',
        );
    }

    public function test_boot_does_not_publish_outside_console(): void
    {
        $app = $this->makeApp(false);
        (new CqFeatureServiceProvider($app))->boot();

        self::assertSame([], ServiceProvider::pathsToPublish(CqFeatureServiceProvider::class, CqFeatureServiceProvider::CONFIG_TAG));
        self::assertSame([], ServiceProvider::pathsToPublish(CqFeatureServiceProvider::class, CqFeatureServiceProvider::STUBS_TAG));
    }

    // ── Auto-discovery — composer.json ────────────────────────────────────

    public function test_composer_json_declares_provider_in_auto_discovery(): void
    {
        $composerPath = realpath(__DIR__.'/../../../composer.json');
        self::assertNotFalse($composerPath);

        $manifest = json_decode((string) file_get_contents($composerPath), true, flags: JSON_THROW_ON_ERROR);

        self::assertContains(
            'Cq\\CqFeature\\Laravel\\CqFeatureServiceProvider',
            $manifest['extra']['laravel']['providers'],
        );
    }

    /**
     * @param  array<string, string>  $paths
     */
    private function publishContainsSource(array $paths, string $expectedSource): bool
    {
        $resolved = realpath($expectedSource);

        foreach (array_keys($paths) as $source) {
            if (realpath($source) === $resolved) {
                return true;
            }
        }

        return false;
    }
}
