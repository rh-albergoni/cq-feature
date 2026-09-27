<?php

declare(strict_types=1);

namespace Cq\CqFeature\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Cq\CqFeature\Config\CqFeatureConfig;
use Cq\CqFeature\Services\PathResolver;
use Cq\CqFeature\Services\RouteRegistry;
use Cq\CqFeature\Services\ScaffoldConfig;
use Cq\CqFeature\Services\ScaffoldService;
use Cq\CqFeature\Services\StubRenderer;

/**
 * Comando Artisan que expõe o gerador de scaffold DDD (Module → Domain → Feature).
 *
 * A `signature` é montada em RUNTIME a partir de
 * {@see CqFeatureConfig::commandName()} (default `feature`, configurável via
 * `config('cqfeature.command.name')`), preservando todos os argumentos, opções e
 * o fluxo interativo do FeatureMaker original (RF-06, RNF-03 — zero hardcode).
 *
 * Os caminhos exibidos/validados (raiz de módulos, agregados) são derivados do
 * {@see CqFeatureConfig} injetado — nenhum literal de caminho/namespace no código.
 */
final class FeatureCommand extends Command
{
    protected $description = 'Gera a estrutura de módulo e features (Shared + Create, Delete, Update, List, Find ou customizadas).';

    public function __construct(private readonly CqFeatureConfig $config)
    {
        $name = $this->config->commandName();

        $this->signature = $name.'
            {module? : Nome da pasta base do módulo (ex.: Financial)}
            {domain? : Nome do domínio (ex.: Finance)}
            {--features= : Lista de features (create,list,find,update,delete,crud ou customizadas em PascalCase)}
            {--force : Criar arquivos sem pedir confirmação}
            {--register-routes : Registrar rotas para os controllers gerados}
            {--aggregate-of= : Criar como agregado dentro de um domínio existente (ex.: Finance)}';

        parent::__construct();
    }

    public function handle(): int
    {
        $module = $this->resolveModule();
        $domain = $this->resolveDomain();

        if ($module === null || $domain === null) {
            $this->error('Parâmetros obrigatórios não informados. Use --help para ver as opções.');

            return self::FAILURE;
        }

        $aggregateOf = $this->resolveAggregateOf($module);

        if ($aggregateOf === false) {
            return self::FAILURE;
        }

        $features = $this->resolveFeatures($domain);

        if ($features === null) {
            $this->error('Parâmetros obrigatórios não informados. Use --help para ver as opções.');

            return self::FAILURE;
        }

        $registerRoutes = $this->resolveRegisterRoutes();

        try {
            $config = ScaffoldConfig::make(
                module: $module,
                domain: $domain,
                features: $features,
                force: (bool) $this->option('force'),
                registerRoutes: $registerRoutes,
                aggregateOf: $aggregateOf,
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $service = $this->makeService($config);
        $plan = $service->plan($config);

        $this->displaySummary($plan, $config);

        if (! $this->option('force') && $this->option('no-interaction')) {
            $this->info('Nenhum arquivo criado. Use --force para criar sem confirmação.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $continue = $this->choiceByIndex(
                'Deseja continuar?',
                [
                    'yes' => 'Sim',
                    'no' => 'Não',
                ],
                'yes',
            );

            if ($continue === 'no') {
                $this->info('Operação cancelada.');

                return self::SUCCESS;
            }
        }

        $result = $service->run($config, $this->getOutput());

        $this->newLine();

        $createdCount = $result->createdCount();
        $this->info("{$createdCount} arquivo(s) criado(s).");

        if ($result->skippedCount() > 0) {
            $this->warn('Arquivos já existentes (não alterados):');
            $this->table(
                ['Path'],
                array_map(fn (string $p) => [$p], $result->getSkipped()),
            );
        }

        if ($result->hasErrors()) {
            $this->error('Erros encontrados:');
            foreach ($result->getErrors() as $error) {
                $this->line("  - {$error}");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Monta a cadeia de serviços já hidratada com o {@see CqFeatureConfig}.
     *
     * PathResolver/StubRenderer/RouteRegistry recebem a config injetada; os
     * caminhos absolutos (app_path/base_path) são resolvidos por eles a partir
     * dos defaults do framework — nenhum literal de caminho aqui (RNF-03).
     */
    private function makeService(ScaffoldConfig $config): ScaffoldService
    {
        $pathResolver = new PathResolver(
            $this->config,
            $config->module,
            $config->domain,
            aggregateOf: $config->aggregateOf,
        );
        $stubRenderer = new StubRenderer($this->config);
        $routeRegistry = $config->registerRoutes ? new RouteRegistry($this->config) : null;

        return new ScaffoldService($pathResolver, $stubRenderer, $routeRegistry);
    }

    private function resolveModule(): ?string
    {
        $module = $this->argument('module');

        if ($module !== null && $module !== '') {
            return $module;
        }

        if ($this->option('no-interaction')) {
            return null;
        }

        return $this->ask('Qual a pasta base do módulo? (ex.: Financial)');
    }

    private function resolveDomain(): ?string
    {
        $domain = $this->argument('domain');

        if ($domain !== null && $domain !== '') {
            return $domain;
        }

        if ($this->option('no-interaction')) {
            return null;
        }

        return $this->ask('Qual o nome do domínio? (ex.: Finance)');
    }

    /**
     * Resolve se o domínio será criado como agregado de um domínio existente.
     *
     * @return string|null|false  string = domínio pai, null = independente, false = erro
     */
    private function resolveAggregateOf(string $module): string|null|false
    {
        $aggregateOfOption = $this->option('aggregate-of');

        if ($aggregateOfOption !== null && $aggregateOfOption !== '') {
            $parentPath = $this->modulesPath($module, $aggregateOfOption);

            if (! is_dir($parentPath)) {
                $this->error("O domínio '{$aggregateOfOption}' não existe em {$this->modulesDisplayPath($module)}/. Não é possível criar um agregado dentro de um domínio inexistente.");

                return false;
            }

            return $aggregateOfOption;
        }

        if ($this->option('no-interaction') || $this->option('force')) {
            return null;
        }

        $choice = $this->choiceByIndex(
            'Como deseja criar esse domínio?',
            [
                'independent' => 'Domínio independente (padrão)',
                'aggregate' => 'Agregado de um domínio existente',
            ],
            'independent',
        );

        if ($choice === 'independent') {
            return null;
        }

        return $this->resolveParentDomainInteractive($module);
    }

    /**
     * Pergunta o domínio pai no modo interativo, listando os domínios existentes.
     *
     * @return string|false  string = domínio pai, false = erro
     */
    private function resolveParentDomainInteractive(string $module): string|false
    {
        $modulePath = $this->modulesPath($module);

        if (! is_dir($modulePath)) {
            $this->error("O módulo '{$module}' não existe em {$this->modulesDisplayPath()}.");

            return false;
        }

        $domains = $this->listExistingDomains($modulePath);

        if (empty($domains)) {
            $this->error("Nenhum domínio encontrado em {$this->modulesDisplayPath($module)}/. Crie um domínio independente primeiro.");

            return false;
        }

        $parentDomain = $this->choice(
            'Qual o domínio pai? (o agregado será criado dentro de '.$this->config->aggregatesSegment().'/)',
            $domains,
        );

        return $parentDomain;
    }

    /**
     * Lista os domínios existentes em um módulo (subdiretórios diretos com nome PascalCase).
     *
     * @return array<string>
     */
    private function listExistingDomains(string $modulePath): array
    {
        $domains = [];

        foreach (scandir($modulePath) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (is_dir($modulePath.'/'.$entry) && preg_match('/^[A-Z][a-zA-Z0-9]*$/', $entry)) {
                $domains[] = $entry;
            }
        }

        sort($domains);

        return $domains;
    }

    private function resolveRegisterRoutes(): bool
    {
        if ($this->option('register-routes')) {
            return true;
        }

        if ($this->option('no-interaction') || $this->option('force')) {
            return false;
        }

        $choice = $this->choiceByIndex(
            'Deseja registrar as rotas automaticamente?',
            [
                'yes' => 'Sim',
                'no' => 'Não',
            ],
            'yes',
        );

        return $choice === 'yes';
    }

    /**
     * @return array<string>|null
     */
    private function resolveFeatures(?string $domain = null): ?array
    {
        $featuresOption = $this->option('features');

        if ($featuresOption !== null && $featuresOption !== '') {
            $features = array_map('trim', explode(',', $featuresOption));

            if ($domain !== null && ! $this->option('no-interaction')) {
                $features = $this->validateCustomFeatureDomainPrefix($features, $domain);
            }

            return $features;
        }

        if ($this->option('no-interaction')) {
            return null;
        }

        $choice = $this->choiceByIndex(
            'Quais features criar?',
            [
                'crud' => 'CRUD completo (create, delete, update, list, find)',
                'custom_standard' => 'Selecionar features padrão individualmente',
                'custom' => 'Criar feature customizada (ex.: ChangeInstallmentStatus)',
            ],
            'crud',
        );

        if ($choice === 'crud') {
            return ['crud'];
        }

        if ($choice === 'custom_standard') {
            return $this->resolveStandardFeaturesInteractive();
        }

        return $this->resolveCustomFeaturesInteractive($domain);
    }

    /**
     * @return array<string>|null
     */
    private function resolveStandardFeaturesInteractive(): ?array
    {
        $available = ScaffoldConfig::allowedFeatures();
        $selected = [];

        foreach ($available as $feature) {
            if ($this->confirm("Incluir feature '{$feature}'?", true)) {
                $selected[] = $feature;
            }
        }

        if (empty($selected)) {
            $this->warn('Nenhuma feature selecionada.');

            return null;
        }

        return $selected;
    }

    /**
     * @return array<string>|null
     */
    private function resolveCustomFeaturesInteractive(?string $domain): ?array
    {
        $featureName = $this->askCustomFeatureName($domain);

        if ($featureName === null) {
            $this->warn('Nenhuma feature selecionada.');

            return null;
        }

        return [$featureName];
    }

    private function askCustomFeatureName(?string $domain): ?string
    {
        $this->line('');
        $this->line('  <comment>Dica:</comment> O nome deve ser em PascalCase sem o prefixo do domínio.');

        if ($domain !== null) {
            $this->line("  <comment>Exemplo:</comment> Para criar {$domain}ChangeInstallmentStatus, digite: <info>ChangeInstallmentStatus</info>");
        }

        $name = $this->ask('Nome da feature customizada (PascalCase, sem prefixo do domínio)');

        if ($name === null || trim($name) === '') {
            return null;
        }

        $name = trim($name);

        if ($domain !== null && str_starts_with($name, $domain)) {
            $withoutPrefix = substr($name, strlen($domain));
            if ($withoutPrefix !== '' && $withoutPrefix !== false) {
                $this->warn("Você informou '{$name}' que já contém o domínio '{$domain}' como prefixo.");
                $this->line("  O scaffold adiciona o domínio automaticamente: {$domain}<info>{$withoutPrefix}</info>");

                if ($this->confirm("Deseja usar '{$withoutPrefix}' (recomendado)?", true)) {
                    $name = $withoutPrefix;
                }
            }
        }

        if (! preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name)) {
            $this->error("O nome '{$name}' é inválido. Use PascalCase (ex.: ChangeInstallmentStatus).");

            return $this->askCustomFeatureName($domain);
        }

        return $name;
    }

    /**
     * Valida features customizadas passadas via --features para garantir
     * que NÃO contenham o prefixo do domínio (o scaffold adiciona automaticamente).
     *
     * @param  array<string>  $features
     * @return array<string>
     */
    private function validateCustomFeatureDomainPrefix(array $features, string $domain): array
    {
        $result = [];

        foreach ($features as $feature) {
            if (ScaffoldConfig::isStandardFeature($feature) || strtolower($feature) === 'crud') {
                $result[] = $feature;

                continue;
            }

            if (str_starts_with($feature, $domain)) {
                $withoutPrefix = substr($feature, strlen($domain));
                if ($withoutPrefix !== '' && $withoutPrefix !== false && preg_match('/^[A-Z]/', $withoutPrefix)) {
                    $this->warn("Feature '{$feature}' contém o prefixo do domínio '{$domain}'.");
                    $this->line("  O scaffold adiciona o domínio automaticamente: {$domain}<info>{$withoutPrefix}</info>");

                    if ($this->confirm("Deseja usar '{$withoutPrefix}' em vez de '{$feature}'?", true)) {
                        $result[] = $withoutPrefix;

                        continue;
                    }
                }
            }

            $result[] = $feature;
        }

        return $result;
    }

    /**
     * Exibe choice com índices numéricos e retorna a chave string original.
     *
     * @param  array<string, string>  $options  [chave => label]
     */
    private function choiceByIndex(string $question, array $options, string $defaultKey): string
    {
        $keys = array_keys($options);
        $labels = array_values($options);
        $defaultIndex = array_search($defaultKey, $keys, true);
        $defaultIndex = $defaultIndex !== false ? $defaultIndex : 0;

        $selected = $this->choice(
            "{$question} (enter vazio = opção {$defaultIndex})",
            $labels,
            $defaultIndex,
        );

        $selectedIndex = array_search($selected, $labels, true);

        return $keys[$selectedIndex];
    }

    /**
     * @param  array{toCreate: array<string>, toSkip: array<string>, isNewDomain: bool}  $plan
     */
    private function displaySummary(array $plan, ScaffoldConfig $config): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════');
        $this->info('  Resumo do Scaffold');
        $this->info('═══════════════════════════════════════════');
        $this->newLine();

        $this->line("  <comment>Módulo:</comment>      {$config->module}");
        $this->line("  <comment>Domínio:</comment>     {$config->domain}");

        if ($config->aggregateOf !== null) {
            $this->line("  <comment>Agregado de:</comment>  {$config->aggregateOf}");
            $basePath = $this->modulesDisplayPath(
                $config->module,
                $config->aggregateOf,
                $this->config->aggregatesSegment(),
                $config->domain,
            );
            $this->line("  <comment>Base path:</comment>    {$basePath}/");
        }

        $featureLabels = array_map(function (string $f) use ($config) {
            if (ScaffoldConfig::isCustomFeature($f)) {
                return $config->domain.$f.' (custom)';
            }

            return $f;
        }, $config->features);

        $this->line('  <comment>Features:</comment>    '.implode(', ', $featureLabels));
        $this->line('  <comment>Tipo:</comment>        '.($plan['isNewDomain'] ? 'Novo (será criado)' : 'Existente'));
        $this->line('  <comment>Rotas:</comment>       '.($config->registerRoutes ? 'Sim (registro automático)' : 'Não'));

        $this->newLine();

        if (count($plan['toCreate']) > 0) {
            $this->info('Arquivos a criar ('.count($plan['toCreate']).'):');
            foreach ($plan['toCreate'] as $path) {
                $this->line("  <info>+</info> {$path}");
            }
        } else {
            $this->warn('Nenhum arquivo novo a criar.');
        }

        if (count($plan['toSkip']) > 0) {
            $this->newLine();
            $this->warn('Arquivos já existentes (serão ignorados: '.count($plan['toSkip']).'):');
            foreach ($plan['toSkip'] as $path) {
                $this->line("  <comment>~</comment> {$path}");
            }
        }

        $this->newLine();
    }

    /**
     * Caminho absoluto dentro da raiz de módulos configurada
     * (ex.: app_path('Modules/Financial')), derivado do {@see CqFeatureConfig}.
     */
    private function modulesPath(string ...$segments): string
    {
        $relative = implode('/', array_merge([$this->config->modulesSegment()], $segments));

        return app_path($relative);
    }

    /**
     * Versão "amigável" (relativa ao app) usada apenas em mensagens, derivada da
     * config — sem literais de caminho hardcoded (RNF-03).
     */
    private function modulesDisplayPath(string ...$segments): string
    {
        return implode('/', array_merge(
            [$this->config->appPath(), $this->config->modulesSegment()],
            $segments,
        ));
    }
}
