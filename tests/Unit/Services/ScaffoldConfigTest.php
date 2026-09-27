<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Unit\Services;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Cq\CqFeature\Services\ScaffoldConfig;

/**
 * Testes do domínio puro {@see ScaffoldConfig}: expansão de `crud`, normalização
 * de features padrão para minúsculas, preservação de PascalCase em customizadas,
 * deduplicação e propagação de flags. Portado 1:1 do FeatureMaker do `cq/api`
 * (RN-04), trocando apenas o namespace.
 */
final class ScaffoldConfigTest extends TestCase
{
    public function test_make_expands_crud_to_five_features(): void
    {
        $config = ScaffoldConfig::make(
            module: 'Financial',
            domain: 'Finance',
            features: ['crud'],
        );

        $this->assertCount(5, $config->features);
        $this->assertContains('create', $config->features);
        $this->assertContains('delete', $config->features);
        $this->assertContains('update', $config->features);
        $this->assertContains('list', $config->features);
        $this->assertContains('find', $config->features);
    }

    public function test_make_keeps_individual_features(): void
    {
        $config = ScaffoldConfig::make(
            module: 'Financial',
            domain: 'Finance',
            features: ['create', 'list'],
        );

        $this->assertCount(2, $config->features);
        $this->assertSame(['create', 'list'], $config->features);
    }

    public function test_make_normalizes_features_to_lowercase(): void
    {
        $config = ScaffoldConfig::make(
            module: 'Financial',
            domain: 'Finance',
            features: ['Create', 'LIST', 'Find'],
        );

        $this->assertSame(['create', 'list', 'find'], $config->features);
    }

    public function test_make_deduplicates_features(): void
    {
        $config = ScaffoldConfig::make(
            module: 'Financial',
            domain: 'Finance',
            features: ['create', 'create', 'list'],
        );

        $this->assertCount(2, $config->features);
        $this->assertSame(['create', 'list'], $config->features);
    }

    public function test_make_deduplicates_crud_with_individual_features(): void
    {
        $config = ScaffoldConfig::make(
            module: 'Financial',
            domain: 'Finance',
            features: ['create', 'crud'],
        );

        $this->assertCount(5, $config->features);
    }

    public function test_make_sets_default_values(): void
    {
        $config = ScaffoldConfig::make(
            module: 'Financial',
            domain: 'Finance',
            features: ['create'],
        );

        $this->assertSame('Financial', $config->module);
        $this->assertSame('Finance', $config->domain);
        $this->assertFalse($config->force);
        $this->assertFalse($config->registerRoutes);
        $this->assertTrue($config->isNewDomain);
        $this->assertNull($config->aggregateOf);
    }

    public function test_make_passes_force_and_register_routes(): void
    {
        $config = ScaffoldConfig::make(
            module: 'Financial',
            domain: 'Finance',
            features: ['create'],
            force: true,
            registerRoutes: true,
            isNewDomain: false,
        );

        $this->assertTrue($config->force);
        $this->assertTrue($config->registerRoutes);
        $this->assertFalse($config->isNewDomain);
    }

    public function test_make_with_crud_uppercase(): void
    {
        $config = ScaffoldConfig::make(
            module: 'Financial',
            domain: 'Finance',
            features: ['CRUD'],
        );

        $this->assertCount(5, $config->features);
        $this->assertContains('create', $config->features);
    }

    public function test_make_trims_feature_whitespace(): void
    {
        $config = ScaffoldConfig::make(
            module: 'Financial',
            domain: 'Finance',
            features: ['  create  ', ' list '],
        );

        $this->assertSame(['create', 'list'], $config->features);
    }

    public function test_make_keeps_custom_feature_pascal_case(): void
    {
        $config = ScaffoldConfig::make(
            module: 'Financial',
            domain: 'Finance',
            features: ['ChangeInstallmentStatus'],
        );

        $this->assertSame(['ChangeInstallmentStatus'], $config->features);
        $this->assertTrue(ScaffoldConfig::isCustomFeature('ChangeInstallmentStatus'));
        $this->assertFalse(ScaffoldConfig::isStandardFeature('ChangeInstallmentStatus'));
    }

    public function test_make_crud_enables_migration_creation(): void
    {
        $config = ScaffoldConfig::make(
            module: 'Financial',
            domain: 'Finance',
            features: ['crud'],
        );

        $this->assertTrue($config->createMigration);
    }

    public function test_make_without_crud_does_not_create_migration(): void
    {
        $config = ScaffoldConfig::make(
            module: 'Financial',
            domain: 'Finance',
            features: ['create', 'list'],
        );

        $this->assertFalse($config->createMigration);
    }

    public function test_make_accepts_aggregate_of(): void
    {
        $config = ScaffoldConfig::make(
            module: 'Financial',
            domain: 'FinanceHistory',
            features: ['crud'],
            aggregateOf: 'Finance',
        );

        $this->assertSame('Finance', $config->aggregateOf);
    }

    public function test_make_rejects_domain_as_aggregate_of_itself(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('não pode ser agregado de si mesmo');

        ScaffoldConfig::make(
            module: 'Financial',
            domain: 'Finance',
            features: ['crud'],
            aggregateOf: 'Finance',
        );
    }

    public function test_allowed_features_returns_five_features(): void
    {
        $allowed = ScaffoldConfig::allowedFeatures();

        $this->assertCount(5, $allowed);
        $this->assertContains('create', $allowed);
        $this->assertContains('delete', $allowed);
        $this->assertContains('update', $allowed);
        $this->assertContains('list', $allowed);
        $this->assertContains('find', $allowed);
        $this->assertSame(ScaffoldConfig::STANDARD_FEATURES, $allowed);
    }

    public function test_make_throws_on_invalid_feature(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Feature customizada 'export' é inválida");

        ScaffoldConfig::make(
            module: 'Financial',
            domain: 'Finance',
            features: ['export'],
        );
    }

    public function test_make_throws_on_empty_features(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('É necessário informar pelo menos uma feature.');

        ScaffoldConfig::make(
            module: 'Financial',
            domain: 'Finance',
            features: [],
        );
    }

    public function test_make_throws_on_path_traversal_module(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('path traversal');

        ScaffoldConfig::make(
            module: '../Secret',
            domain: 'Finance',
            features: ['create'],
        );
    }

    public function test_make_with_submodule_path(): void
    {
        $config = ScaffoldConfig::make(
            module: 'Financial/Sub',
            domain: 'Finance',
            features: ['create'],
        );

        $this->assertSame('Financial/Sub', $config->module);
    }
}
