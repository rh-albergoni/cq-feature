<?php

declare(strict_types=1);

namespace Cq\CqFeature\Config;

/**
 * Value Object imutável com a configuração do CqFeature.
 *
 * Hidratado a partir do array `config('cqfeature')` (mesma forma de
 * {@see config/cqfeature.php}) via {@see self::fromArray()}, aplicando os
 * defaults que reproduzem EXATAMENTE o comportamento do FeatureMaker no
 * projeto `cq/api` (RN-01). É a peça central do "zero hardcode": todas as
 * classes (PathResolver, RouteRegistry, StubRenderer) recebem este VO por
 * construtor em vez de literais.
 *
 * Imutável: não há setters; o estado é fixado no construtor (propriedades
 * `readonly`). Arrays retornados pelos getters são cópias por valor (semântica
 * copy-on-write do PHP), portanto não afetam o estado interno.
 *
 * `fromArray()` é tolerante a chaves ausentes: um `config/cqfeature.php`
 * parcial publicado pelo consumidor continua funcionando, pois cada chave
 * faltante recai no default (RNF-04).
 */
final class CqFeatureConfig
{
    /**
     * @param  array<string, string>  $segments
     * @param  array<string, string>  $paths
     * @param  array<string, string>  $sharedSubfolders
     * @param  array<string, string>  $featureSubfolders
     * @param  array<int, string>  $routeMiddleware
     * @param  array<string, array{method: string, suffix: string}>  $routeFeatureMap
     */
    private function __construct(
        private readonly string $rootNamespace,
        private readonly array $segments,
        private readonly array $paths,
        private readonly array $sharedSubfolders,
        private readonly array $featureSubfolders,
        private readonly string $routesBase,
        private readonly string $routePrefixStrategy,
        private readonly array $routeMiddleware,
        private readonly array $routeFeatureMap,
        private readonly string $commandName,
        private readonly string $packageStubsPath,
        private readonly ?string $publishedStubsPath,
    ) {}

    /**
     * Defaults idênticos ao FeatureMaker do `cq/api` (RN-01).
     *
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return [
            'root_namespace' => 'App',
            'segments' => [
                'modules' => 'Modules',
                'shared' => 'Shared',
                'features' => 'Features',
                'aggregates' => 'Aggregates',
            ],
            'paths' => [
                'app' => 'app',
                'tests_base' => 'tests/Feature/Modules',
                'factories_base' => 'database/factories/Modules',
                'migrations_base' => 'database/migrations',
            ],
            'shared_subfolders' => [
                'Entity' => 'Entities',
                'Model' => 'Models',
                'CommandDao' => 'Dao/Commands',
                'QueryDao' => 'Dao/Queries',
                'CommandRepository' => 'Repositories/Commands',
                'QueryRepository' => 'Repositories/Queries',
            ],
            'feature_subfolders' => [
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
            ],
            'routes' => [
                'base' => 'routes/api/modules',
                'prefix' => 'module_kebab',
                'middleware' => ['api', 'JWT', 'cors', 'localization'],
                'feature_map' => [
                    'create' => ['method' => 'post', 'suffix' => ''],
                    'list' => ['method' => 'get', 'suffix' => ''],
                    'find' => ['method' => 'get', 'suffix' => '/{id}'],
                    'update' => ['method' => 'put', 'suffix' => '/{id}'],
                    'delete' => ['method' => 'delete', 'suffix' => '/{id}'],
                ],
            ],
            'command' => [
                'name' => 'feature',
            ],
            'stubs' => [
                'package_path' => self::defaultPackageStubsPath(),
                'published_path' => null,
            ],
        ];
    }

    /**
     * Caminho default dos stubs internos ao pacote (raiz-do-pacote/stubs).
     */
    private static function defaultPackageStubsPath(): string
    {
        return dirname(__DIR__, 2).'/stubs';
    }

    /**
     * Hidrata o VO a partir do array de configuração.
     *
     * Chaves ausentes recaem nos defaults; chaves presentes sobrescrevem
     * apenas o ponto correspondente (override parcial). Mapas de subpastas,
     * segmentos e paths são mesclados sobre os defaults (preservando as chaves
     * não informadas); listas (middleware) e mapas de rota (feature_map),
     * quando presentes, substituem o default por completo.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $defaults = self::defaults();

        $segments = self::mergeMap($defaults['segments'], $data['segments'] ?? []);
        $paths = self::mergeMap($defaults['paths'], $data['paths'] ?? []);
        $sharedSubfolders = self::mergeMap($defaults['shared_subfolders'], $data['shared_subfolders'] ?? []);
        $featureSubfolders = self::mergeMap($defaults['feature_subfolders'], $data['feature_subfolders'] ?? []);

        $routes = is_array($data['routes'] ?? null) ? $data['routes'] : [];
        $command = is_array($data['command'] ?? null) ? $data['command'] : [];
        $stubs = is_array($data['stubs'] ?? null) ? $data['stubs'] : [];

        $publishedStubsPath = $stubs['published_path'] ?? $defaults['stubs']['published_path'];

        return new self(
            rootNamespace: (string) ($data['root_namespace'] ?? $defaults['root_namespace']),
            segments: $segments,
            paths: $paths,
            sharedSubfolders: $sharedSubfolders,
            featureSubfolders: $featureSubfolders,
            routesBase: (string) ($routes['base'] ?? $defaults['routes']['base']),
            routePrefixStrategy: (string) ($routes['prefix'] ?? $defaults['routes']['prefix']),
            routeMiddleware: array_values(self::asStringList($routes['middleware'] ?? $defaults['routes']['middleware'])),
            routeFeatureMap: is_array($routes['feature_map'] ?? null) ? $routes['feature_map'] : $defaults['routes']['feature_map'],
            commandName: (string) ($command['name'] ?? $defaults['command']['name']),
            packageStubsPath: (string) ($stubs['package_path'] ?? $defaults['stubs']['package_path']),
            publishedStubsPath: $publishedStubsPath === null ? null : (string) $publishedStubsPath,
        );
    }

    /**
     * Mescla um override (parcial) sobre o mapa default, preservando as chaves
     * não informadas. Override não-array é ignorado (mantém o default).
     *
     * @param  array<string, string>  $defaults
     * @param  mixed  $override
     * @return array<string, string>
     */
    private static function mergeMap(array $defaults, mixed $override): array
    {
        if (! is_array($override)) {
            return $defaults;
        }

        return array_merge($defaults, $override);
    }

    /**
     * Normaliza um valor para lista de strings (defensivo contra config inválida).
     *
     * @return array<int, string>
     */
    private static function asStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_map(static fn (mixed $item): string => (string) $item, $value);
    }

    /** Namespace raiz PSR-4 do app consumidor. Default: 'App'. */
    public function rootNamespace(): string
    {
        return $this->rootNamespace;
    }

    /** Segmento de módulos. Default: 'Modules'. */
    public function modulesSegment(): string
    {
        return $this->segments['modules'];
    }

    /** Segmento de artefatos compartilhados do domínio. Default: 'Shared'. */
    public function sharedSegment(): string
    {
        return $this->segments['shared'];
    }

    /** Segmento de features. Default: 'Features'. */
    public function featuresSegment(): string
    {
        return $this->segments['features'];
    }

    /** Segmento de agregados. Default: 'Aggregates'. */
    public function aggregatesSegment(): string
    {
        return $this->segments['aggregates'];
    }

    /** Raiz do código-fonte do app. Default: 'app'. */
    public function appPath(): string
    {
        return $this->paths['app'];
    }

    /** Raiz dos testes de Feature gerados. Default: 'tests/Feature/Modules'. */
    public function testsBase(): string
    {
        return $this->paths['tests_base'];
    }

    /** Raiz das factories geradas. Default: 'database/factories/Modules'. */
    public function factoriesBase(): string
    {
        return $this->paths['factories_base'];
    }

    /** Raiz das migrations geradas. Default: 'database/migrations'. */
    public function migrationsBase(): string
    {
        return $this->paths['migrations_base'];
    }

    /**
     * Mapa [artefato => subpasta] dos arquivos Shared.
     * Default: ['Entity'=>'Entities', 'Model'=>'Models', 'CommandDao'=>'Dao/Commands', ...].
     *
     * @return array<string, string>
     */
    public function sharedSubfolders(): array
    {
        return $this->sharedSubfolders;
    }

    /**
     * Mapa [artefato => subpasta] dos arquivos de Feature.
     * Default: ['Controller'=>'Controllers', 'Service'=>'Services', 'Request'=>'Requests', ...].
     *
     * @return array<string, string>
     */
    public function featureSubfolders(): array
    {
        return $this->featureSubfolders;
    }

    /** Pasta-base dos arquivos de rota. Default: 'routes/api/modules'. */
    public function routesBase(): string
    {
        return $this->routesBase;
    }

    /**
     * Middleware aplicado ao Route::group gerado.
     * Default: ['api', 'JWT', 'cors', 'localization'].
     *
     * @return array<int, string>
     */
    public function routeMiddleware(): array
    {
        return $this->routeMiddleware;
    }

    /** Estratégia de prefixo do Route::group. Default: 'module_kebab'. */
    public function routePrefixStrategy(): string
    {
        return $this->routePrefixStrategy;
    }

    /**
     * Mapa feature => {método HTTP, sufixo da URI} (ex-FEATURE_ROUTE_MAP).
     * Default: create/list/find/update/delete.
     *
     * @return array<string, array{method: string, suffix: string}>
     */
    public function routeFeatureMap(): array
    {
        return $this->routeFeatureMap;
    }

    /** Nome do comando Artisan. Default: 'feature'. */
    public function commandName(): string
    {
        return $this->commandName;
    }

    /** Diretório dos stubs internos ao pacote. Default: raiz-do-pacote/stubs. */
    public function packageStubsPath(): string
    {
        return $this->packageStubsPath;
    }

    /**
     * Diretório de stubs publicados no projeto (precedência sobre os do pacote),
     * ou null quando não publicados. Default: null.
     */
    public function publishedStubsPath(): ?string
    {
        return $this->publishedStubsPath;
    }
}
