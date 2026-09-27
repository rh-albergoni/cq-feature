<?php

declare(strict_types=1);

namespace Cq\CqFeature\Services;

/**
 * DTO de configuração para o scaffold de features.
 *
 * Contém módulo, domínio, lista de features (com expansão de crud),
 * flags de force/registerRoutes e indicador de domínio novo.
 *
 * Features padrão (create, delete, update, list, find) são normalizadas para lowercase.
 * Features customizadas (ex.: ChangeInstallmentStatus) são mantidas em PascalCase.
 *
 * Domínio puro (RN-04): não depende de {@see \Cq\CqFeature\Config\CqFeatureConfig}
 * — trata apenas de expansão/normalização/validação dos nomes informados pelo comando.
 */
final class ScaffoldConfig
{
    /**
     * Features expandidas a partir do alias `crud`.
     *
     * @var array<int, string>
     */
    public const CRUD_FEATURES = ['create', 'delete', 'update', 'list', 'find'];

    /**
     * Features padrão (CRUD) reconhecidas e normalizadas para minúsculas.
     *
     * @var array<int, string>
     */
    public const STANDARD_FEATURES = ['create', 'delete', 'update', 'list', 'find'];

    /**
     * @param  string  $module  Pasta base do módulo (ex.: Financial)
     * @param  string  $domain  Nome do domínio (ex.: Finance)
     * @param  array<string>  $features  Lista de features normalizadas
     * @param  bool  $force  Criar sem confirmação
     * @param  bool  $registerRoutes  Registrar rotas
     * @param  bool  $isNewDomain  Se o domínio é novo (diretório não existe)
     * @param  bool  $createMigration  Criar migração (apenas quando o desenvolvedor seleciona CRUD)
     * @param  string|null  $aggregateOf  Nome do domínio pai quando criando dentro de Aggregates (ex.: Finance)
     */
    public function __construct(
        public readonly string $module,
        public readonly string $domain,
        public readonly array $features,
        public readonly bool $force = false,
        public readonly bool $registerRoutes = false,
        public readonly bool $isNewDomain = true,
        public readonly bool $createMigration = false,
        public readonly ?string $aggregateOf = null,
    ) {
        ScaffoldConfigValidator::validate($this->module, $this->domain, $this->features, $this->aggregateOf);
    }

    /**
     * Factory que recebe features brutas e expande crud, normaliza e deduplica.
     *
     * @param  array<string>  $features
     * @param  bool|null  $createMigration  Se null, calculado a partir de $features (crud)
     * @param  string|null  $aggregateOf  Nome do domínio pai quando criando dentro de Aggregates
     */
    public static function make(
        string $module,
        string $domain,
        array $features,
        bool $force = false,
        bool $registerRoutes = false,
        bool $isNewDomain = true,
        ?bool $createMigration = null,
        ?string $aggregateOf = null,
    ): self {
        $expanded = self::expandFeatures($features);
        $createMigration = $createMigration ?? self::shouldCreateMigration($features);

        return new self(
            module: $module,
            domain: $domain,
            features: $expanded,
            force: $force,
            registerRoutes: $registerRoutes,
            isNewDomain: $isNewDomain,
            createMigration: $createMigration,
            aggregateOf: $aggregateOf,
        );
    }

    /**
     * Verifica se uma migração deve ser criada (apenas quando o desenvolvedor seleciona CRUD).
     *
     * @param  array<string>  $rawFeatures
     */
    private static function shouldCreateMigration(array $rawFeatures): bool
    {
        foreach ($rawFeatures as $feature) {
            if (strtolower(trim($feature)) === 'crud') {
                return true;
            }
        }

        return false;
    }

    /**
     * Expande 'crud' para as cinco features padrão, normaliza padrão para minúsculas,
     * mantém PascalCase para customizadas e remove duplicatas.
     *
     * @param  array<string>  $features
     * @return array<string>
     */
    private static function expandFeatures(array $features): array
    {
        $expanded = [];
        foreach ($features as $feature) {
            $trimmed = trim($feature);
            $lower = strtolower($trimmed);

            if ($lower === 'crud') {
                $expanded = array_merge($expanded, self::CRUD_FEATURES);
            } elseif (in_array($lower, self::STANDARD_FEATURES, true)) {
                $expanded[] = $lower;
            } else {
                $expanded[] = $trimmed;
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * Retorna as features padrão permitidas.
     *
     * @return array<string>
     */
    public static function allowedFeatures(): array
    {
        return self::STANDARD_FEATURES;
    }

    /**
     * Verifica se uma feature é padrão (CRUD).
     */
    public static function isStandardFeature(string $feature): bool
    {
        return in_array(strtolower($feature), self::STANDARD_FEATURES, true);
    }

    /**
     * Verifica se uma feature é customizada (não-CRUD).
     */
    public static function isCustomFeature(string $feature): bool
    {
        return ! self::isStandardFeature($feature);
    }
}
