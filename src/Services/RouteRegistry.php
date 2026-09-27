<?php

declare(strict_types=1);

namespace Cq\CqFeature\Services;

use Illuminate\Support\Str;
use Cq\CqFeature\Config\CqFeatureConfig;
use RuntimeException;

/**
 * Cria ou atualiza o arquivo de rotas para os controllers gerados pelo scaffold.
 *
 * Caminho-base, estratégia de prefixo, middleware e o mapa feature→método HTTP
 * vêm todos do {@see CqFeatureConfig} injetado — ZERO literais de
 * path/middleware/prefixo/mapa no código (RNF-03). Com a config default a saída
 * é idêntica à do FeatureMaker do `cq/api` (RN-01):
 *
 *   {routes_base}/{module_kebab}/{domain_plural_kebab}.php
 *   ex.: routes/api/modules/financial/finances.php
 *
 *   Route::group([
 *       'middleware' => ['api', 'JWT', 'cors', 'localization'],
 *       'prefix' => '{module_kebab}',
 *   ], function () { ... });
 *
 * Rotas duplicadas (mesmo método + URI) são detectadas e não adicionadas
 * novamente — o append é idempotente por `Route::{method}('{uri}'` (RF-04).
 */
final class RouteRegistry
{
    /**
     * Identificador do artefato Controller em {@see CqFeatureConfig::featureSubfolders()}.
     * Usado para derivar o segmento de namespace do controller (default 'Controllers').
     */
    private const CONTROLLER_KEY = 'Controller';

    private readonly string $basePath;

    /**
     * @param  CqFeatureConfig  $config  Configuração injetada (fonte única de path/prefixo/middleware/mapa)
     * @param  string|null  $basePath  Raiz absoluta do projeto (para testes; padrão base_path())
     */
    public function __construct(
        private readonly CqFeatureConfig $config,
        ?string $basePath = null,
    ) {
        $this->basePath = $basePath ?? base_path();
    }

    /**
     * Registra rotas para as features indicadas do domínio.
     *
     * Se o arquivo de rotas não existir, cria com estrutura completa.
     * Se existir, adiciona apenas rotas que ainda não estejam presentes (append
     * idempotente por método+URI).
     *
     * @param  string  $module  Nome do módulo (ex.: Financial)
     * @param  string  $domain  Nome do domínio (ex.: Finance)
     * @param  array<int, string>  $features  Features a registrar (ex.: ['create', 'list', 'find'])
     * @return array{path: string, added: array<int, string>, skipped: array<int, string>}
     */
    public function register(string $module, string $domain, array $features): array
    {
        $routeFilePath = $this->resolveRoutePath($module, $domain);
        $prefix = $this->resolvePrefix($module, $domain);
        $slug = $this->resolveSlug($domain);
        $controllerNamespace = $this->resolveControllerNamespace($module, $domain);

        $routeDefinitions = $this->buildRouteDefinitions($domain, $slug, $features, $controllerNamespace);

        if (file_exists($routeFilePath)) {
            return $this->updateExistingFile($routeFilePath, $routeDefinitions);
        }

        return $this->createNewFile($routeFilePath, $prefix, $slug, $routeDefinitions);
    }

    /**
     * Resolve o path absoluto do arquivo de rotas a partir da base configurada.
     *
     * Ex. (default): {basePath}/routes/api/modules/{module_kebab}/{domain_plural_kebab}.php
     */
    public function resolveRoutePath(string $module, string $domain): string
    {
        $moduleKebab = Str::kebab($module);
        $domainPluralKebab = $this->resolveSlug($domain);

        return $this->basePath.'/'.$this->config->routesBase().'/'.$moduleKebab.'/'.$domainPluralKebab.'.php';
    }

    /**
     * Slug (segmento da URI) do domínio: plural em kebab-case.
     */
    private function resolveSlug(string $domain): string
    {
        return Str::plural(Str::kebab($domain));
    }

    /**
     * Resolve o prefixo do Route::group conforme a estratégia configurada
     * ({@see CqFeatureConfig::routePrefixStrategy()}). Default: 'module_kebab'.
     */
    private function resolvePrefix(string $module, string $domain): string
    {
        return match ($this->config->routePrefixStrategy()) {
            'domain_kebab' => Str::kebab($domain),
            'domain_plural_kebab' => $this->resolveSlug($domain),
            default => Str::kebab($module),
        };
    }

    /**
     * Namespace base dos controllers de feature, derivado da config
     * (root_namespace, modules e features segments) — sem literais.
     *
     * Ex. (default): App\Modules\{Module}\{Domain}\Features
     */
    private function resolveControllerNamespace(string $module, string $domain): string
    {
        $moduleNamespace = str_replace('/', '\\', $module);

        return implode('\\', [
            $this->config->rootNamespace(),
            $this->config->modulesSegment(),
            $moduleNamespace,
            $domain,
            $this->config->featuresSegment(),
        ]);
    }

    /**
     * Segmento de namespace da subpasta de Controllers (default 'Controllers'),
     * derivado de {@see CqFeatureConfig::featureSubfolders()} (slashes viram
     * separador de namespace).
     */
    private function controllerSubNamespace(): string
    {
        $subfolder = $this->config->featureSubfolders()[self::CONTROLLER_KEY] ?? self::CONTROLLER_KEY;

        return str_replace('/', '\\', $subfolder);
    }

    /**
     * Constrói as definições de rota para cada feature.
     *
     * Features padrão vêm do mapa configurável ({@see CqFeatureConfig::routeFeatureMap()});
     * features customizadas (PascalCase) geram `POST {slug}/{action-kebab}`.
     * Features não-reconhecidas são silenciosamente ignoradas.
     *
     * @param  array<int, string>  $features
     * @return array<int, array{method: string, uri: string, controller: string, controllerClass: string, line: string}>
     */
    private function buildRouteDefinitions(
        string $domain,
        string $slug,
        array $features,
        string $controllerNamespace,
    ): array {
        $featureMap = $this->config->routeFeatureMap();
        $controllerSubNamespace = $this->controllerSubNamespace();
        $definitions = [];

        foreach ($features as $feature) {
            $normalized = strtolower($feature);

            if (isset($featureMap[$normalized])) {
                $map = $featureMap[$normalized];
                $action = ucfirst($normalized);
                $uri = $slug.$map['suffix'];
                $method = $map['method'];
            } elseif (preg_match('/^[A-Z][a-zA-Z0-9]*$/', $feature) === 1) {
                $action = $feature;
                $uri = $slug.'/'.Str::kebab($action);
                $method = 'post';
            } else {
                continue;
            }

            $controllerClass = "{$domain}{$action}Controller";
            $fullControllerClass = "{$controllerNamespace}\\{$domain}{$action}\\{$controllerSubNamespace}\\{$controllerClass}";

            $definitions[] = [
                'method' => $method,
                'uri' => $uri,
                'controller' => $fullControllerClass,
                'controllerClass' => $controllerClass,
                'line' => "    Route::{$method}('{$uri}', [{$controllerClass}::class, 'execute']);",
            ];
        }

        return $definitions;
    }

    /**
     * Cria um novo arquivo de rotas com todas as definições.
     *
     * @param  array<int, array{method: string, uri: string, controller: string, controllerClass: string, line: string}>  $definitions
     * @return array{path: string, added: array<int, string>, skipped: array<int, string>}
     */
    private function createNewFile(string $path, string $prefix, string $slug, array $definitions): array
    {
        $directory = dirname($path);
        if (! is_dir($directory)) {
            if (! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new RuntimeException("Não foi possível criar o diretório: {$directory}");
            }
        }

        $useStatements = $this->buildUseStatements($definitions);
        $routeLines = $this->buildRouteLines($definitions);
        $middleware = $this->formatMiddleware();

        $content = "<?php\n\n"
            .$useStatements
            ."use Illuminate\\Support\\Facades\\Route;\n\n"
            ."// {$prefix}/{$slug}\n\n"
            ."Route::group([\n"
            ."    'middleware' => {$middleware},\n"
            ."    'prefix' => '{$prefix}',\n"
            ."], function () {\n"
            .$routeLines
            ."});\n";

        file_put_contents($path, $content);

        $added = array_map(
            static fn (array $def): string => "Route::{$def['method']}('{$def['uri']}', ...)",
            $definitions,
        );

        return [
            'path' => $path,
            'added' => $added,
            'skipped' => [],
        ];
    }

    /**
     * Atualiza arquivo existente adicionando apenas rotas que não existam.
     *
     * @param  array<int, array{method: string, uri: string, controller: string, controllerClass: string, line: string}>  $definitions
     * @return array{path: string, added: array<int, string>, skipped: array<int, string>}
     */
    private function updateExistingFile(string $path, array $definitions): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Não foi possível ler o arquivo: {$path}");
        }

        $added = [];
        $skipped = [];
        $newUseStatements = [];
        $newRouteLines = [];

        foreach ($definitions as $def) {
            if ($this->routeExistsInContent($content, $def['method'], $def['uri'])) {
                $skipped[] = "Route::{$def['method']}('{$def['uri']}', ...)";

                continue;
            }

            $useStatement = "use {$def['controller']};";
            if (! str_contains($content, $useStatement)) {
                $newUseStatements[] = $useStatement;
            }

            $newRouteLines[] = $def['line'];
            $added[] = "Route::{$def['method']}('{$def['uri']}', ...)";
        }

        if ($newRouteLines === []) {
            return ['path' => $path, 'added' => $added, 'skipped' => $skipped];
        }

        $content = $this->insertUseStatements($content, $newUseStatements);
        $content = $this->insertRouteLines($content, $newRouteLines);

        file_put_contents($path, $content);

        return ['path' => $path, 'added' => $added, 'skipped' => $skipped];
    }

    /**
     * Formata a lista de middleware da config como literal PHP de array.
     * Ex.: ['api', 'JWT', 'cors', 'localization'].
     */
    private function formatMiddleware(): string
    {
        $middleware = $this->config->routeMiddleware();

        if ($middleware === []) {
            return '[]';
        }

        return "['".implode("', '", $middleware)."']";
    }

    /**
     * Verifica se uma rota com o mesmo método e URI já existe no conteúdo.
     */
    private function routeExistsInContent(string $content, string $method, string $uri): bool
    {
        $escapedUri = preg_quote($uri, '/');
        $pattern = "/Route::{$method}\s*\(\s*['\"]".$escapedUri."['\"]/";

        return preg_match($pattern, $content) === 1;
    }

    /**
     * Insere use statements antes da linha "use Illuminate\Support\Facades\Route;".
     *
     * @param  array<int, string>  $useStatements
     */
    private function insertUseStatements(string $content, array $useStatements): string
    {
        if ($useStatements === []) {
            return $content;
        }

        $newUses = implode("\n", $useStatements)."\n";

        if (str_contains($content, 'use Illuminate\Support\Facades\Route;')) {
            return str_replace(
                'use Illuminate\Support\Facades\Route;',
                $newUses.'use Illuminate\Support\Facades\Route;',
                $content,
            );
        }

        $phpTag = "<?php\n\n";
        if (str_starts_with($content, $phpTag)) {
            return $phpTag.$newUses.substr($content, strlen($phpTag));
        }

        return $newUses.$content;
    }

    /**
     * Insere novas linhas de rota antes do fechamento do grupo "});".
     *
     * @param  array<int, string>  $routeLines
     */
    private function insertRouteLines(string $content, array $routeLines): string
    {
        if ($routeLines === []) {
            return $content;
        }

        $newLines = implode("\n", $routeLines)."\n";
        $closingPattern = '});';

        $lastPos = strrpos($content, $closingPattern);
        if ($lastPos === false) {
            return $content."\n".$newLines;
        }

        return substr($content, 0, $lastPos).$newLines.$closingPattern.substr($content, $lastPos + strlen($closingPattern));
    }

    /**
     * Monta o bloco de use statements dos controllers.
     *
     * @param  array<int, array{method: string, uri: string, controller: string, controllerClass: string, line: string}>  $definitions
     */
    private function buildUseStatements(array $definitions): string
    {
        $lines = array_map(
            static fn (array $def): string => "use {$def['controller']};",
            $definitions,
        );

        return implode("\n", $lines)."\n";
    }

    /**
     * Monta as linhas de rota dentro do grupo.
     *
     * @param  array<int, array{method: string, uri: string, controller: string, controllerClass: string, line: string}>  $definitions
     */
    private function buildRouteLines(array $definitions): string
    {
        $lines = array_map(
            static fn (array $def): string => $def['line'],
            $definitions,
        );

        return implode("\n", $lines)."\n";
    }
}
