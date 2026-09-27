<?php

declare(strict_types=1);

namespace Cq\CqFeature\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Cq\CqFeature\Config\CqFeatureConfig;

/**
 * Resolve caminhos absolutos e namespaces para Shared e Features dentro da raiz
 * de módulos configurada (default: app/Modules).
 *
 * Todos os segmentos (Modules, Shared, Features, Aggregates), subpastas de
 * artefatos e bases de filesystem (tests/factories/migrations) vêm do
 * {@see CqFeatureConfig} injetado — ZERO literais de caminho/namespace (RNF-03).
 * Com a config default, a saída é idêntica à do FeatureMaker do `cq/api`
 * (RN-01).
 *
 * Estrutura (config default):
 *   app/Modules/{Module}/{Domain}/Shared/...
 *   app/Modules/{Module}/{Domain}/Features/{Domain}{Action}/...
 *
 * Quando `aggregateOf` é informado, os paths ficam dentro de Aggregates do
 * domínio pai:
 *   app/Modules/{Module}/{ParentDomain}/Aggregates/{Domain}/Shared/...
 *   app/Modules/{Module}/{ParentDomain}/Aggregates/{Domain}/Features/{Domain}{Action}/...
 *
 * Suporta features padrão (create, delete, update, list, find) e customizadas
 * (PascalCase).
 */
final class PathResolver
{
    /**
     * Composição de artefatos por feature padrão.
     *
     * Define APENAS quais artefatos cada feature gera (lógica de domínio, não
     * caminhos): a subpasta e o nome do arquivo de cada artefato são resolvidos
     * a partir de {@see CqFeatureConfig::featureSubfolders()}. A chave
     * `Readme` é tratada como caso especial (README.md na raiz da feature).
     *
     * @var array<string, array<int, string>>
     */
    private const FEATURE_SHAPES = [
        'create' => ['Controller', 'Service', 'Request', 'Dto', 'CommandRepository', 'Readme'],
        'update' => ['Controller', 'Service', 'Request', 'Dto', 'CommandRepository', 'Readme'],
        'delete' => ['Controller', 'Service', 'CommandRepository', 'Readme'],
        'list' => ['Controller', 'Service', 'Request', 'FilterDto', 'Dto', 'QueryDao', 'Readme'],
        'find' => ['Controller', 'Service', 'ViewDto', 'QueryDao', 'QueryRepository', 'Readme'],
    ];

    /**
     * Composição de artefatos de uma feature customizada (gera o conjunto completo).
     *
     * @var array<int, string>
     */
    private const CUSTOM_FEATURE_SHAPE = [
        'Controller', 'Service', 'Request', 'Dto',
        'CommandRepository', 'QueryRepository', 'QueryDao', 'CommandDao', 'Readme',
    ];

    /** Identificador do artefato README (sem subpasta). */
    private const README_KEY = 'Readme';

    /** Identificador do artefato Model em Shared (arquivo sem sufixo: {Domain}.php). */
    private const MODEL_KEY = 'Model';

    /** Identificador do ViewDto (nome de arquivo sem a action: {Domain}ViewDto.php). */
    private const VIEW_DTO_KEY = 'ViewDto';

    private readonly string $basePath;

    private readonly string $projectRoot;

    /**
     * @param  CqFeatureConfig  $config  Configuração injetada (fonte única de caminhos/namespaces)
     * @param  string  $module  Pasta base do módulo (ex.: Financial)
     * @param  string  $domain  Nome do domínio (ex.: Finance)
     * @param  string|null  $appPath  Caminho absoluto base do app (para testes; padrão app_path())
     * @param  string|null  $aggregateOf  Nome do domínio pai quando criando dentro de Aggregates
     */
    public function __construct(
        private readonly CqFeatureConfig $config,
        private readonly string $module,
        private readonly string $domain,
        ?string $appPath = null,
        private readonly ?string $aggregateOf = null,
    ) {
        $base = $appPath ?? app_path();

        $modulesRoot = $base.'/'.$this->config->modulesSegment();

        if ($this->aggregateOf !== null) {
            $this->basePath = $modulesRoot.'/'.$this->module.'/'.$this->aggregateOf
                .'/'.$this->config->aggregatesSegment().'/'.$this->domain;
        } else {
            $this->basePath = $modulesRoot.'/'.$this->module.'/'.$this->domain;
        }

        $this->projectRoot = dirname($base);

        $this->ensurePathWithinBounds($base);
    }

    /**
     * Garante que o path resolvido está dentro da raiz de módulos configurada
     * (default: app/Modules), bloqueando path traversal (RNF-02).
     *
     * A verificação normaliza lexicalmente os segmentos `.` e `..` antes de
     * comparar, de modo que qualquer tentativa de "escapar" da raiz de módulos
     * (ex.: `../Evil`) seja efetivamente detectada — e não apenas mascarada por
     * uma comparação de prefixo de string.
     */
    private function ensurePathWithinBounds(string $appPath): void
    {
        $modulesDir = $this->normalizePath($appPath.DIRECTORY_SEPARATOR.$this->config->modulesSegment());
        $normalizedBase = $this->normalizePath($this->basePath);

        $isWithin = $normalizedBase === $modulesDir
            || str_starts_with($normalizedBase, $modulesDir.DIRECTORY_SEPARATOR);

        if (! $isWithin) {
            throw new InvalidArgumentException(
                'O path resolvido está fora do diretório de módulos configurado.'
            );
        }
    }

    /**
     * Normaliza um path resolvendo lexicalmente os segmentos `.` e `..` e
     * unificando os separadores, sem tocar no filesystem.
     */
    private function normalizePath(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $isAbsolute = str_starts_with($path, DIRECTORY_SEPARATOR);

        $segments = [];
        foreach (explode(DIRECTORY_SEPARATOR, $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return ($isAbsolute ? DIRECTORY_SEPARATOR : '').implode(DIRECTORY_SEPARATOR, $segments);
    }

    /**
     * Path base do domínio.
     */
    public function domainPath(): string
    {
        return $this->basePath;
    }

    /**
     * Path da pasta Shared.
     */
    public function sharedPath(): string
    {
        return $this->basePath.'/'.$this->config->sharedSegment();
    }

    /**
     * Path da pasta Features.
     */
    public function featuresPath(): string
    {
        return $this->basePath.'/'.$this->config->featuresSegment();
    }

    /**
     * Retorna todos os paths de arquivos Shared.
     *
     * @return array<string, string> Mapa [identificador => path absoluto]
     */
    public function sharedFiles(): array
    {
        $shared = $this->sharedPath();
        $files = [];

        foreach ($this->config->sharedSubfolders() as $key => $subfolder) {
            $suffix = $key === self::MODEL_KEY ? '' : $key;
            $files[$key] = $shared.'/'.$subfolder.'/'.$this->domain.$suffix.'.php';
        }

        return $files;
    }

    /**
     * Path do TestCase compartilhado do domínio.
     * Ex.: tests/Feature/Modules/Financial/Finance/Shared/FinanceTestCase.php
     */
    public function testCasePath(): string
    {
        return $this->testsBasePath().'/'.$this->config->sharedSegment().'/'.$this->domain.'TestCase.php';
    }

    /**
     * Path do arquivo de teste de uma feature específica.
     * Ex.: tests/Feature/Modules/Financial/Finance/Features/FinanceCreate/FinanceCreateTest.php
     */
    public function featureTestPath(string $feature): string
    {
        $featureDir = $this->domain.$this->resolveAction($feature);

        return $this->testsBasePath().'/'.$this->config->featuresSegment().'/'.$featureDir.'/'.$featureDir.'Test.php';
    }

    /**
     * Base path da pasta de testes do domínio (Feature tests).
     */
    private function testsBasePath(): string
    {
        $base = $this->projectRoot.'/'.$this->config->testsBase().'/'.$this->module;

        if ($this->aggregateOf !== null) {
            return $base.'/'.$this->aggregateOf.'/'.$this->config->aggregatesSegment().'/'.$this->domain;
        }

        return $base.'/'.$this->domain;
    }

    /**
     * Path do arquivo de factory (espelha Shared/Models para a convenção HasFactory do Laravel).
     * Ex.: database/factories/Modules/Financial/Finance/Shared/Models/FinanceFactory.php
     */
    public function factoryPath(): string
    {
        $modelSubfolder = $this->config->sharedSubfolders()[self::MODEL_KEY];
        $suffix = '/'.$this->config->sharedSegment().'/'.$modelSubfolder.'/'.$this->domain.'Factory.php';
        $base = $this->projectRoot.'/'.$this->config->factoriesBase().'/'.$this->module;

        if ($this->aggregateOf !== null) {
            return $base.'/'.$this->aggregateOf.'/'.$this->config->aggregatesSegment().'/'.$this->domain.$suffix;
        }

        return $base.'/'.$this->domain.$suffix;
    }

    /**
     * Path do arquivo de migration.
     * Ex.: database/migrations/2025_01_01_000000_create_finances_table.php
     */
    public function migrationPath(): string
    {
        return $this->projectRoot.'/'.$this->config->migrationsBase().'/'
            .date('Y_m_d_His').'_create_'.$this->tableName().'_table.php';
    }

    /**
     * Nome da tabela no banco (snake_case plural do domain).
     */
    public function tableName(): string
    {
        return Str::snake(Str::plural($this->domain));
    }

    /**
     * Retorna o nome do domínio pai (aggregateOf), ou null se não for agregado.
     */
    public function aggregateOf(): ?string
    {
        return $this->aggregateOf;
    }

    /**
     * Segmento de agregados configurado (default: 'Aggregates').
     *
     * Exposto para que os colaboradores que compõem o placeholder {{Module}}
     * (ex.: {@see ScaffoldService}) derivem o segmento da config em vez de um
     * literal — mantendo o "zero hardcode" (RNF-03) também no namespace
     * renderizado dos agregados.
     */
    public function aggregatesSegment(): string
    {
        return $this->config->aggregatesSegment();
    }

    /**
     * Path do domínio pai (usado para validar que o domínio pai existe).
     * Retorna null se não for agregado.
     */
    public function parentDomainPath(): ?string
    {
        if ($this->aggregateOf === null) {
            return null;
        }

        return dirname($this->basePath, 2);
    }

    /**
     * Resolve a action PascalCase a partir do nome da feature.
     *
     * Para features padrão (create, update, etc.), converte para ucfirst(lower).
     * Para features customizadas (PascalCase), mantém como está.
     */
    public function resolveAction(string $feature): string
    {
        if ($this->isStandardFeature($feature)) {
            return ucfirst(strtolower($feature));
        }

        return $feature;
    }

    /**
     * Path de uma feature específica (ex.: FinanceCreate ou FinanceChangeInstallmentStatus).
     */
    public function featurePath(string $feature): string
    {
        return $this->featuresPath().'/'.$this->domain.$this->resolveAction($feature);
    }

    /**
     * Retorna todos os paths de arquivos de uma feature.
     *
     * @return array<string, string> Mapa [identificador => path absoluto]
     */
    public function featureFiles(string $feature): array
    {
        if ($this->isStandardFeature($feature)) {
            return $this->buildFeatureFiles($feature, $this->resolveAction($feature), self::FEATURE_SHAPES[strtolower($feature)]);
        }

        return $this->buildFeatureFiles($feature, $feature, self::CUSTOM_FEATURE_SHAPE);
    }

    /**
     * Retorna todos os paths de todas as features informadas.
     *
     * @param  array<string>  $features
     * @return array<string, array<string, string>> Mapa [feature => [identificador => path]]
     */
    public function allFeatureFiles(array $features): array
    {
        $result = [];
        foreach ($features as $feature) {
            $result[$feature] = $this->featureFiles($feature);
        }

        return $result;
    }

    /**
     * Retorna o namespace base do domínio.
     */
    public function baseNamespace(): string
    {
        $moduleNamespace = str_replace('/', '\\', $this->module);

        $segments = [
            $this->config->rootNamespace(),
            $this->config->modulesSegment(),
            $moduleNamespace,
        ];

        if ($this->aggregateOf !== null) {
            $segments[] = $this->aggregateOf;
            $segments[] = $this->config->aggregatesSegment();
        }

        $segments[] = $this->domain;

        return implode('\\', $segments);
    }

    /**
     * Retorna o namespace do Shared.
     */
    public function sharedNamespace(): string
    {
        return $this->baseNamespace().'\\'.$this->config->sharedSegment();
    }

    /**
     * Retorna o namespace de uma feature.
     */
    public function featureNamespace(string $feature): string
    {
        return $this->baseNamespace().'\\'.$this->config->featuresSegment().'\\'.$this->domain.$this->resolveAction($feature);
    }

    /**
     * Constrói o mapa de arquivos de uma feature a partir da sua composição de
     * artefatos, resolvendo subpasta e nome de arquivo via config.
     *
     * @param  array<int, string>  $shape  Lista ordenada de identificadores de artefato
     * @return array<string, string> Mapa [identificador => path absoluto]
     */
    private function buildFeatureFiles(string $feature, string $action, array $shape): array
    {
        $featurePath = $this->featurePath($feature);
        $featureSubfolders = $this->config->featureSubfolders();
        $files = [];

        foreach ($shape as $key) {
            if ($key === self::README_KEY) {
                $files[$key] = $featurePath.'/README.md';

                continue;
            }

            // ViewDto compartilha a subpasta de Dto, mas o nome do arquivo não
            // usa a action: {Domain}ViewDto.php.
            $actionPart = $key === self::VIEW_DTO_KEY ? '' : $action;

            $files[$key] = $featurePath.'/'.$featureSubfolders[$key].'/'.$this->domain.$actionPart.$key.'.php';
        }

        return $files;
    }

    /**
     * Indica se a feature é uma das features padrão (CRUD), de forma
     * case-insensitive.
     */
    private function isStandardFeature(string $feature): bool
    {
        return array_key_exists(strtolower($feature), self::FEATURE_SHAPES);
    }
}
