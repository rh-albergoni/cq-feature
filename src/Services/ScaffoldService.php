<?php

declare(strict_types=1);

namespace Cq\CqFeature\Services;

use Illuminate\Support\Str;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Orquestra o scaffold de um domínio (novo ou existente). Ex-`FeatureMakerService`.
 *
 * Detecta automaticamente se o domínio já existe no disco:
 * - Domínio novo → cria Shared + features selecionadas (RN-03).
 * - Domínio existente → cria apenas as features selecionadas (sem Shared).
 *
 * Arquivos já existentes são sempre ignorados (skipped) e nunca sobrescritos.
 * Erros de I/O são capturados por item e agregados em {@see ScaffoldResult}.
 *
 * Recebe {@see PathResolver}, {@see StubRenderer} e (opcional) {@see RouteRegistry}
 * por construtor — todos já hidratados com {@see \Cq\CqFeature\Config\CqFeatureConfig}.
 * O serviço não conhece caminhos/namespaces literais (RNF-03); com a config default
 * a saída é idêntica à do FeatureMaker do `cq/api` (RN-01).
 *
 * Suporta features padrão (create, delete, update, list, find) e customizadas (PascalCase).
 */
final class ScaffoldService
{
    /**
     * Mapeamento de identificadores do PathResolver::sharedFiles()
     * para nomes de stub no StubRenderer (dot notation).
     */
    private const SHARED_STUB_MAP = [
        'Entity' => 'Shared.Entity',
        'Model' => 'Shared.Model',
        'CommandDao' => 'Shared.Dao.CommandDao',
        'QueryDao' => 'Shared.Dao.QueryDao',
        'CommandRepository' => 'Shared.Repositories.CommandRepository',
        'QueryRepository' => 'Shared.Repositories.QueryRepository',
    ];

    /**
     * Mapeamento de identificadores do PathResolver::featureFiles()
     * para nomes de stub relativos dentro de Features.{Action}.
     *
     * O stub final será "Features.{Action}.{stubSuffix}" para padrão
     * ou "Features.Custom.{stubSuffix}" para customizadas.
     */
    private const FEATURE_STUB_MAP = [
        'Controller' => 'Controller',
        'Service' => 'Service',
        'Request' => 'Request',
        'Dto' => 'Dto',
        'CommandRepository' => 'CommandRepository',
        'FilterDto' => 'FilterDto',
        'ViewDto' => 'ViewDto',
        'QueryDao' => 'QueryDao',
        'QueryRepository' => 'QueryRepository',
        'CommandDao' => 'CommandDao',
        'Readme' => 'Readme',
    ];

    public function __construct(
        private readonly PathResolver $pathResolver,
        private readonly StubRenderer $stubRenderer,
        private readonly ?RouteRegistry $routeRegistry = null,
    ) {}

    /**
     * Retorna o plano de arquivos que seriam criados/ignorados, sem escrever no disco.
     *
     * O plano cobre, para domínio novo, Shared + Factory + (Migration) + TestCase +
     * Features + Tests; para domínio existente, apenas TestCase + Features + Tests.
     *
     * @return array{toCreate: array<string>, toSkip: array<string>, isNewDomain: bool}
     */
    public function plan(ScaffoldConfig $config): array
    {
        $isNewDomain = $this->detectIsNewDomain($config);
        $effectiveConfig = $this->reconcileDomainState($config, $isNewDomain);

        $plan = $this->buildPlan($effectiveConfig);

        $toCreate = [];
        $toSkip = [];

        foreach ($plan as $item) {
            if (file_exists($item['path'])) {
                $toSkip[] = $item['path'];
            } else {
                $toCreate[] = $item['path'];
            }
        }

        return [
            'toCreate' => $toCreate,
            'toSkip' => $toSkip,
            'isNewDomain' => $isNewDomain,
        ];
    }

    /**
     * Executa o scaffold de um domínio.
     *
     * Detecta automaticamente se o domínio já existe (diretório presente).
     * - Domínio novo → Shared + features selecionadas.
     * - Domínio existente → apenas features selecionadas (sem Shared).
     *
     * Arquivos já existentes são ignorados (skipped) e nunca sobrescritos.
     * Cada item é gravado em try/catch isolado: uma falha de I/O é acumulada em
     * {@see ScaffoldResult::getErrors()} e os demais itens prosseguem.
     *
     * @param  OutputInterface|null  $output  Para exibir progresso (usado pelo Command)
     */
    public function run(ScaffoldConfig $config, ?OutputInterface $output = null): ScaffoldResult
    {
        $isNewDomain = $this->detectIsNewDomain($config);
        $effectiveConfig = $this->reconcileDomainState($config, $isNewDomain);

        $plan = $this->buildPlan($effectiveConfig);

        $created = [];
        $skipped = [];
        $errors = [];

        foreach ($plan as $item) {
            $path = $item['path'];
            $stubName = $item['stub'];
            $replace = $item['replace'];

            if (file_exists($path)) {
                $skipped[] = $path;
                $this->writeOutput($output, "  <comment>SKIP</comment> {$path} (já existe)");

                continue;
            }

            try {
                $directory = dirname($path);
                if (! is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }

                $content = $this->stubRenderer->render($stubName, $replace);
                file_put_contents($path, $content);

                $created[] = $path;
                $this->writeOutput($output, "  <info>CREATE</info> {$path}");
            } catch (Throwable $e) {
                $errors[] = "Erro ao criar {$path}: {$e->getMessage()}";
                $this->writeOutput($output, "  <error>ERROR</error> {$path}: {$e->getMessage()}");
            }
        }

        if ($config->registerRoutes && $this->routeRegistry !== null) {
            try {
                $routeResult = $this->routeRegistry->register(
                    $config->module,
                    $config->domain,
                    $config->features,
                );

                if (! empty($routeResult['added'])) {
                    $this->writeOutput($output, '');
                    $this->writeOutput($output, "  <info>ROUTES</info> {$routeResult['path']}");
                    foreach ($routeResult['added'] as $route) {
                        $this->writeOutput($output, "    <info>+</info> {$route}");
                    }
                }

                if (! empty($routeResult['skipped'])) {
                    foreach ($routeResult['skipped'] as $route) {
                        $this->writeOutput($output, "    <comment>~</comment> {$route} (já existe)");
                    }
                }
            } catch (Throwable $e) {
                $errors[] = "Erro ao registrar rotas: {$e->getMessage()}";
                $this->writeOutput($output, "  <error>ERROR</error> Rotas: {$e->getMessage()}");
            }
        }

        return new ScaffoldResult($created, $skipped, $errors, $isNewDomain);
    }

    /**
     * Reconcilia o estado de domínio novo/existente detectado no disco com o
     * declarado na config, regerando a config quando divergem (preservando os
     * demais parâmetros).
     */
    private function reconcileDomainState(ScaffoldConfig $config, bool $isNewDomain): ScaffoldConfig
    {
        if ($isNewDomain === $config->isNewDomain) {
            return $config;
        }

        return ScaffoldConfig::make(
            module: $config->module,
            domain: $config->domain,
            features: $config->features,
            force: $config->force,
            registerRoutes: $config->registerRoutes,
            isNewDomain: $isNewDomain,
            createMigration: $config->createMigration,
            aggregateOf: $config->aggregateOf,
        );
    }

    /**
     * Detecta se o domínio é novo verificando a existência do diretório base.
     */
    private function detectIsNewDomain(ScaffoldConfig $config): bool
    {
        $basePath = $this->pathResolver->domainPath();

        return ! is_dir($basePath);
    }

    /**
     * Monta o plano completo de arquivos a processar.
     *
     * Shared primeiro, depois cada feature na ordem da config.
     *
     * @return array<array{path: string, stub: string, replace: array<string, string>}>
     */
    private function buildPlan(ScaffoldConfig $config): array
    {
        $plan = [];

        if ($config->isNewDomain) {
            $sharedReplace = $this->buildSharedReplace($config);
            $sharedFiles = $this->pathResolver->sharedFiles();

            foreach ($sharedFiles as $identifier => $path) {
                if (! isset(self::SHARED_STUB_MAP[$identifier])) {
                    continue;
                }

                $plan[] = [
                    'path' => $path,
                    'stub' => self::SHARED_STUB_MAP[$identifier],
                    'replace' => $sharedReplace,
                ];
            }

            $scaffoldReplace = $this->buildScaffoldReplace($config);

            $plan[] = [
                'path' => $this->pathResolver->factoryPath(),
                'stub' => 'Factories.ModelFactory',
                'replace' => $scaffoldReplace,
            ];

            if ($config->createMigration) {
                $plan[] = [
                    'path' => $this->pathResolver->migrationPath(),
                    'stub' => 'Migrations.create_table',
                    'replace' => $scaffoldReplace,
                ];
            }
        }

        $plan[] = $this->buildTestCasePlanItem($config);

        foreach ($config->features as $feature) {
            $isCustom = ScaffoldConfig::isCustomFeature($feature);
            $action = $this->pathResolver->resolveAction($feature);
            $featureReplace = $this->buildFeatureReplace($config, $action);
            $featureFiles = $this->pathResolver->featureFiles($feature);

            foreach ($featureFiles as $identifier => $path) {
                if (! isset(self::FEATURE_STUB_MAP[$identifier])) {
                    continue;
                }

                $stubSuffix = self::FEATURE_STUB_MAP[$identifier];
                $stubName = $identifier === 'Readme'
                    ? 'Features.Readme'
                    : ($isCustom ? "Features.Custom.{$stubSuffix}" : "Features.{$action}.{$stubSuffix}");

                $plan[] = [
                    'path' => $path,
                    'stub' => $stubName,
                    'replace' => $featureReplace,
                ];
            }

            $plan[] = $this->buildFeatureTestPlanItem($config, $feature);
        }

        return $plan;
    }

    /**
     * @return array<string, string>
     */
    private function buildSharedReplace(ScaffoldConfig $config): array
    {
        return $this->buildScaffoldReplace($config);
    }

    /**
     * @return array<string, string>
     */
    private function buildScaffoldReplace(ScaffoldConfig $config): array
    {
        $domainSnake = Str::snake($config->domain);

        return [
            'Module' => $this->resolveModulePlaceholder($config),
            'Domain' => $config->domain,
            'module_kebab' => Str::kebab($config->module),
            'domain_plural_kebab' => Str::plural(Str::kebab($config->domain)),
            'table_name' => $this->pathResolver->tableName(),
            'domain_camel' => lcfirst($config->domain),
            'domain_snake' => $domainSnake,
            'domain_snake_plural' => Str::plural($domainSnake),
        ];
    }

    /**
     * Plano para o TestCase compartilhado do dominio (sempre presente; sera "skipped" se ja existir).
     *
     * @return array{path: string, stub: string, replace: array<string, string>}
     */
    private function buildTestCasePlanItem(ScaffoldConfig $config): array
    {
        return [
            'path' => $this->pathResolver->testCasePath(),
            'stub' => 'Tests.TestCase',
            'replace' => $this->buildScaffoldReplace($config),
        ];
    }

    /**
     * Plano para o teste de uma feature especifica (um arquivo por feature).
     *
     * @return array{path: string, stub: string, replace: array<string, string>}
     */
    private function buildFeatureTestPlanItem(ScaffoldConfig $config, string $feature): array
    {
        $isCustom = ScaffoldConfig::isCustomFeature($feature);
        $action = $this->pathResolver->resolveAction($feature);

        $stubName = $isCustom
            ? 'Tests.Features.Custom'
            : 'Tests.Features.'.$action;

        $replace = array_merge(
            $this->buildScaffoldReplace($config),
            $this->buildFeatureReplace($config, $action),
            [
                'action_snake' => Str::snake($action),
            ],
        );

        return [
            'path' => $this->pathResolver->featureTestPath($feature),
            'stub' => $stubName,
            'replace' => $replace,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildFeatureReplace(ScaffoldConfig $config, string $action): array
    {
        return array_merge(
            $this->buildScaffoldReplace($config),
            [
                'Action' => $action,
                'action_kebab' => Str::kebab($action),
            ],
        );
    }

    /**
     * Resolve o valor do placeholder {{Module}} para stubs.
     *
     * Quando aggregateOf é definido, o Module inclui o path do domínio pai + Aggregates,
     * para que o namespace gerado fique correto:
     *   {RootNamespace}\Modules\{Module}\{ParentDomain}\Aggregates\{Domain}\...
     */
    private function resolveModulePlaceholder(ScaffoldConfig $config): string
    {
        if ($config->aggregateOf !== null) {
            return $config->module.'\\'.$config->aggregateOf.'\\'.$this->pathResolver->aggregatesSegment();
        }

        return $config->module;
    }

    private function writeOutput(?OutputInterface $output, string $message): void
    {
        $output?->writeln($message);
    }
}
