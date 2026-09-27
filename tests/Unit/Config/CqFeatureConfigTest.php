<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Cq\CqFeature\Config\CqFeatureConfig;
use ReflectionClass;

/**
 * Testes unitários do VO {@see CqFeatureConfig}.
 *
 * Cobrem: defaults idênticos ao FeatureMaker do `cq/api` (RN-01), aplicação
 * de overrides parciais (preservando os demais defaults), imutabilidade e
 * sobrescrita de middleware/feature_map de rotas.
 */
final class CqFeatureConfigTest extends TestCase
{
    public function test_defaults_completos_reproduzem_o_feature_maker(): void
    {
        $config = CqFeatureConfig::fromArray([]);

        self::assertSame('App', $config->rootNamespace());

        self::assertSame('Modules', $config->modulesSegment());
        self::assertSame('Shared', $config->sharedSegment());
        self::assertSame('Features', $config->featuresSegment());
        self::assertSame('Aggregates', $config->aggregatesSegment());

        self::assertSame('app', $config->appPath());
        self::assertSame('tests/Feature/Modules', $config->testsBase());
        self::assertSame('database/factories/Modules', $config->factoriesBase());
        self::assertSame('database/migrations', $config->migrationsBase());

        self::assertSame([
            'Entity' => 'Entities',
            'Model' => 'Models',
            'CommandDao' => 'Dao/Commands',
            'QueryDao' => 'Dao/Queries',
            'CommandRepository' => 'Repositories/Commands',
            'QueryRepository' => 'Repositories/Queries',
        ], $config->sharedSubfolders());

        self::assertSame([
            'Controller' => 'Controllers',
            'Service' => 'Services',
            'Request' => 'Requests',
            'Dto' => 'Dtos',
            'ViewDto' => 'Dtos',
            'FilterDto' => 'FilterDtos',
            'CommandRepository' => 'Repositories/Commands',
            'QueryRepository' => 'Repositories/Queries',
            'CommandDao' => 'Dao/Commands',
            'QueryDao' => 'Dao/Queries',
        ], $config->featureSubfolders());

        self::assertSame('routes/api/modules', $config->routesBase());
        self::assertSame('module_kebab', $config->routePrefixStrategy());
        self::assertSame(['api', 'JWT', 'cors', 'localization'], $config->routeMiddleware());
        self::assertSame([
            'create' => ['method' => 'post', 'suffix' => ''],
            'list' => ['method' => 'get', 'suffix' => ''],
            'find' => ['method' => 'get', 'suffix' => '/{id}'],
            'update' => ['method' => 'put', 'suffix' => '/{id}'],
            'delete' => ['method' => 'delete', 'suffix' => '/{id}'],
        ], $config->routeFeatureMap());

        self::assertSame('feature', $config->commandName());

        self::assertStringEndsWith('/stubs', $config->packageStubsPath());
        self::assertNull($config->publishedStubsPath());
    }

    public function test_default_package_stubs_path_aponta_para_raiz_do_pacote(): void
    {
        $config = CqFeatureConfig::fromArray([]);

        $packageRoot = dirname(__DIR__, 3);

        self::assertSame($packageRoot.'/stubs', $config->packageStubsPath());
    }

    public function test_override_parcial_preserva_os_demais_defaults(): void
    {
        $config = CqFeatureConfig::fromArray(['root_namespace' => 'Acme']);

        self::assertSame('Acme', $config->rootNamespace());

        // Demais chaves mantêm os defaults.
        self::assertSame('Modules', $config->modulesSegment());
        self::assertSame('tests/Feature/Modules', $config->testsBase());
        self::assertSame('feature', $config->commandName());
        self::assertSame(['api', 'JWT', 'cors', 'localization'], $config->routeMiddleware());
    }

    public function test_override_parcial_de_mapa_aninhado_preserva_chaves_nao_informadas(): void
    {
        $config = CqFeatureConfig::fromArray([
            'segments' => ['modules' => 'Domains'],
            'paths' => ['app' => 'src'],
        ]);

        // Sobrescritos.
        self::assertSame('Domains', $config->modulesSegment());
        self::assertSame('src', $config->appPath());

        // Preservados (não informados no override).
        self::assertSame('Shared', $config->sharedSegment());
        self::assertSame('Features', $config->featuresSegment());
        self::assertSame('Aggregates', $config->aggregatesSegment());
        self::assertSame('tests/Feature/Modules', $config->testsBase());
    }

    public function test_imutabilidade_sem_setters_e_arrays_retornados_nao_afetam_o_interno(): void
    {
        $reflection = new ReflectionClass(CqFeatureConfig::class);

        // Não há setters públicos.
        foreach ($reflection->getMethods() as $method) {
            self::assertStringStartsNotWith('set', $method->getName());
        }

        // Todas as propriedades são readonly.
        foreach ($reflection->getProperties() as $property) {
            self::assertTrue(
                $property->isReadOnly(),
                "A propriedade '{$property->getName()}' deve ser readonly (imutabilidade)."
            );
        }

        // Mutar o array retornado não afeta o estado interno.
        $config = CqFeatureConfig::fromArray([]);
        $middleware = $config->routeMiddleware();
        $middleware[] = 'hacked';

        self::assertSame(['api', 'JWT', 'cors', 'localization'], $config->routeMiddleware());

        $shared = $config->sharedSubfolders();
        $shared['Entity'] = 'Hacked';

        self::assertSame('Entities', $config->sharedSubfolders()['Entity']);
    }

    public function test_override_de_middleware_e_feature_map_de_rotas(): void
    {
        $config = CqFeatureConfig::fromArray([
            'routes' => [
                'middleware' => ['web', 'auth'],
                'feature_map' => [
                    'create' => ['method' => 'post', 'suffix' => '/new'],
                ],
            ],
        ]);

        self::assertSame(['web', 'auth'], $config->routeMiddleware());
        self::assertSame([
            'create' => ['method' => 'post', 'suffix' => '/new'],
        ], $config->routeFeatureMap());

        // routes.base e routes.prefix não informados mantêm defaults.
        self::assertSame('routes/api/modules', $config->routesBase());
        self::assertSame('module_kebab', $config->routePrefixStrategy());
    }

    public function test_valores_invalidos_recaem_nos_defaults(): void
    {
        // Tipos inválidos (não-array) nas chaves estruturais devem ser
        // tolerados, recaindo nos defaults (config parcial/corrompida).
        $config = CqFeatureConfig::fromArray([
            'segments' => 'invalid',
            'paths' => 123,
            'shared_subfolders' => null,
            'feature_subfolders' => false,
            'routes' => 'nope',
            'command' => 7,
            'stubs' => 'x',
        ]);

        self::assertSame('Modules', $config->modulesSegment());
        self::assertSame('app', $config->appPath());
        self::assertSame('Entities', $config->sharedSubfolders()['Entity']);
        self::assertSame('Controllers', $config->featureSubfolders()['Controller']);
        self::assertSame('routes/api/modules', $config->routesBase());
        self::assertSame(['api', 'JWT', 'cors', 'localization'], $config->routeMiddleware());
        self::assertSame('feature', $config->commandName());
        self::assertNull($config->publishedStubsPath());
    }

    public function test_middleware_invalido_vira_lista_vazia(): void
    {
        $config = CqFeatureConfig::fromArray([
            'routes' => ['middleware' => 'api'],
        ]);

        self::assertSame([], $config->routeMiddleware());
    }

    public function test_override_de_command_name_e_stubs(): void
    {
        $config = CqFeatureConfig::fromArray([
            'command' => ['name' => 'make:feature'],
            'stubs' => [
                'package_path' => '/pkg/stubs',
                'published_path' => '/app/stubs/cqfeature',
            ],
        ]);

        self::assertSame('make:feature', $config->commandName());
        self::assertSame('/pkg/stubs', $config->packageStubsPath());
        self::assertSame('/app/stubs/cqfeature', $config->publishedStubsPath());
    }
}
