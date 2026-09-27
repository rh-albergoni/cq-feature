<?php

declare(strict_types=1);

namespace Cq\CqFeature\Services;

use InvalidArgumentException;

/**
 * Validação de module, domain e features para o scaffold.
 *
 * Garante que nomes não estejam vazios, não contenham path traversal
 * e respeitem o formato PSR-4 para namespaces válidos (RN-04 / RNF-02).
 *
 * Features padrão (create, delete, update, list, find) são validadas contra a lista permitida.
 * Features customizadas devem estar em PascalCase e começar com letra maiúscula.
 *
 * Domínio puro: independe de {@see \Cq\CqFeature\Config\CqFeatureConfig} —
 * as regras de nomenclatura/segurança são intrínsecas ao gerador.
 */
final class ScaffoldConfigValidator
{
    /**
     * Padrão PSR-4: alfanuméricos e barra (para subpastas de module).
     * Cada segmento deve começar com letra maiúscula.
     */
    private const MODULE_PATTERN = '/^[A-Z][a-zA-Z0-9]*(\/[A-Z][a-zA-Z0-9]*)*$/';

    /**
     * Padrão PSR-4 para domain: apenas alfanuméricos, começa com maiúscula.
     */
    private const DOMAIN_PATTERN = '/^[A-Z][a-zA-Z0-9]*$/';

    /**
     * Padrão para features customizadas: PascalCase, apenas letras e números.
     */
    private const CUSTOM_FEATURE_PATTERN = '/^[A-Z][a-zA-Z0-9]*$/';

    /**
     * @param  array<string>  $features
     *
     * @throws InvalidArgumentException
     */
    public static function validate(string $module, string $domain, array $features, ?string $aggregateOf = null): void
    {
        self::validateModule($module);
        self::validateDomain($domain);
        self::validateFeatures($features);

        if ($aggregateOf !== null) {
            self::validateAggregateOf($aggregateOf, $domain);
        }
    }

    /**
     * Valida o domínio pai (aggregateOf) para criação dentro de Aggregates.
     *
     * @throws InvalidArgumentException
     */
    public static function validateAggregateOf(string $aggregateOf, string $domain): void
    {
        $trimmed = trim($aggregateOf);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Nome do domínio pai (aggregateOf) não pode ser vazio.');
        }

        if (! preg_match(self::DOMAIN_PATTERN, $trimmed)) {
            throw new InvalidArgumentException(
                "Nome do domínio pai '{$trimmed}' contém caracteres inválidos. "
                .'Use apenas letras e números, começando com maiúscula (ex.: Finance).'
            );
        }

        if ($trimmed === $domain) {
            throw new InvalidArgumentException(
                "O domínio '{$domain}' não pode ser agregado de si mesmo."
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function validateModule(string $module): void
    {
        $trimmed = trim($module);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Pasta base do módulo é obrigatória.');
        }

        if (str_contains($trimmed, '..')) {
            throw new InvalidArgumentException('Pasta base do módulo contém path traversal inválido (..).');
        }

        if (! preg_match(self::MODULE_PATTERN, $trimmed)) {
            throw new InvalidArgumentException(
                'Pasta base do módulo contém caracteres inválidos. '
                .'Use apenas letras e números, começando com maiúscula. '
                .'Para submódulos, separe com barra (ex.: Financial/Sub).'
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function validateDomain(string $domain): void
    {
        $trimmed = trim($domain);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Nome do domínio é obrigatório.');
        }

        if (str_contains($trimmed, '..')) {
            throw new InvalidArgumentException('Nome do domínio contém path traversal inválido (..).');
        }

        if (! preg_match(self::DOMAIN_PATTERN, $trimmed)) {
            throw new InvalidArgumentException(
                'Nome do domínio contém caracteres inválidos. '
                .'Use apenas letras e números, começando com maiúscula (ex.: Finance).'
            );
        }
    }

    /**
     * @param  array<string>  $features
     *
     * @throws InvalidArgumentException
     */
    public static function validateFeatures(array $features): void
    {
        if (empty($features)) {
            throw new InvalidArgumentException('É necessário informar pelo menos uma feature.');
        }

        $standard = ScaffoldConfig::allowedFeatures();

        foreach ($features as $feature) {
            if (in_array($feature, $standard, true)) {
                continue;
            }

            self::validateCustomFeature($feature);
        }
    }

    /**
     * Valida uma feature customizada (não-CRUD).
     *
     * @throws InvalidArgumentException
     */
    public static function validateCustomFeature(string $feature): void
    {
        if (! preg_match(self::CUSTOM_FEATURE_PATTERN, $feature)) {
            throw new InvalidArgumentException(
                "Feature customizada '{$feature}' é inválida. "
                .'Use PascalCase com apenas letras e números, começando com maiúscula '
                .'(ex.: ChangeInstallmentStatus).'
            );
        }
    }
}
