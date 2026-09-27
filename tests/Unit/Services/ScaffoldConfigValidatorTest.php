<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Unit\Services;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Cq\CqFeature\Services\ScaffoldConfigValidator;

/**
 * Testes das validações preservadas (RN-04 / RNF-02) do {@see ScaffoldConfigValidator}:
 * PascalCase/PSR-4 de module e domain, anti path-traversal (`..`) e PascalCase de
 * features customizadas, com mensagens em pt-BR. Portado 1:1 do `cq/api`.
 */
final class ScaffoldConfigValidatorTest extends TestCase
{
    // ── Module ─────────────────────────────────────────────────────────

    public function test_validate_module_rejects_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pasta base do módulo é obrigatória.');

        ScaffoldConfigValidator::validateModule('');
    }

    public function test_validate_module_rejects_whitespace_only(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pasta base do módulo é obrigatória.');

        ScaffoldConfigValidator::validateModule('   ');
    }

    public function test_validate_module_rejects_path_traversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('path traversal');

        ScaffoldConfigValidator::validateModule('../etc');
    }

    public function test_validate_module_rejects_path_traversal_middle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('path traversal');

        ScaffoldConfigValidator::validateModule('Financial/../Secret');
    }

    public function test_validate_module_rejects_lowercase_start(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('caracteres inválidos');

        ScaffoldConfigValidator::validateModule('financial');
    }

    public function test_validate_module_rejects_special_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('caracteres inválidos');

        ScaffoldConfigValidator::validateModule('Fin@ncial');
    }

    public function test_validate_module_rejects_spaces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('caracteres inválidos');

        ScaffoldConfigValidator::validateModule('Financial Module');
    }

    public function test_validate_module_rejects_backslash(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('caracteres inválidos');

        ScaffoldConfigValidator::validateModule('Financial\\Sub');
    }

    public function test_validate_module_accepts_valid_name(): void
    {
        ScaffoldConfigValidator::validateModule('Financial');
        $this->assertTrue(true);
    }

    public function test_validate_module_accepts_valid_submodule(): void
    {
        ScaffoldConfigValidator::validateModule('Financial/Sub');
        $this->assertTrue(true);
    }

    public function test_validate_module_accepts_deep_submodule(): void
    {
        ScaffoldConfigValidator::validateModule('Financial/Sub/Deep');
        $this->assertTrue(true);
    }

    public function test_validate_module_accepts_alphanumeric(): void
    {
        ScaffoldConfigValidator::validateModule('Module2');
        $this->assertTrue(true);
    }

    public function test_validate_module_rejects_submodule_lowercase_segment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('caracteres inválidos');

        ScaffoldConfigValidator::validateModule('Financial/sub');
    }

    // ── Domain ─────────────────────────────────────────────────────────

    public function test_validate_domain_rejects_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nome do domínio é obrigatório.');

        ScaffoldConfigValidator::validateDomain('');
    }

    public function test_validate_domain_rejects_whitespace_only(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nome do domínio é obrigatório.');

        ScaffoldConfigValidator::validateDomain('   ');
    }

    public function test_validate_domain_rejects_path_traversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('path traversal');

        ScaffoldConfigValidator::validateDomain('..');
    }

    public function test_validate_domain_rejects_path_traversal_embedded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('path traversal');

        ScaffoldConfigValidator::validateDomain('Finance..Test');
    }

    public function test_validate_domain_rejects_lowercase_start(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('caracteres inválidos');

        ScaffoldConfigValidator::validateDomain('finance');
    }

    public function test_validate_domain_rejects_slash(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('caracteres inválidos');

        ScaffoldConfigValidator::validateDomain('Finance/Sub');
    }

    public function test_validate_domain_rejects_special_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('caracteres inválidos');

        ScaffoldConfigValidator::validateDomain('Fin@nce');
    }

    public function test_validate_domain_rejects_spaces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('caracteres inválidos');

        ScaffoldConfigValidator::validateDomain('Finance Test');
    }

    public function test_validate_domain_rejects_hyphen(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('caracteres inválidos');

        ScaffoldConfigValidator::validateDomain('Finance-Type');
    }

    public function test_validate_domain_accepts_valid_name(): void
    {
        ScaffoldConfigValidator::validateDomain('Finance');
        $this->assertTrue(true);
    }

    public function test_validate_domain_accepts_alphanumeric(): void
    {
        ScaffoldConfigValidator::validateDomain('Finance2');
        $this->assertTrue(true);
    }

    public function test_validate_domain_accepts_camel_case(): void
    {
        ScaffoldConfigValidator::validateDomain('FinanceType');
        $this->assertTrue(true);
    }

    // ── Features ───────────────────────────────────────────────────────

    public function test_validate_features_rejects_empty_array(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('É necessário informar pelo menos uma feature.');

        ScaffoldConfigValidator::validateFeatures([]);
    }

    public function test_validate_features_rejects_invalid_feature(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Feature customizada 'export' é inválida");

        ScaffoldConfigValidator::validateFeatures(['export']);
    }

    public function test_validate_features_rejects_mixed_valid_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Feature customizada 'import' é inválida");

        ScaffoldConfigValidator::validateFeatures(['create', 'import']);
    }

    public function test_validate_features_accepts_valid_features(): void
    {
        ScaffoldConfigValidator::validateFeatures(['create', 'delete', 'update', 'list', 'find']);
        $this->assertTrue(true);
    }

    public function test_validate_features_accepts_custom_pascal_case(): void
    {
        ScaffoldConfigValidator::validateFeatures(['ChangeInstallmentStatus']);
        $this->assertTrue(true);
    }

    public function test_validate_features_accepts_single_feature(): void
    {
        ScaffoldConfigValidator::validateFeatures(['create']);
        $this->assertTrue(true);
    }

    // ── AggregateOf ────────────────────────────────────────────────────

    public function test_validate_aggregate_of_rejects_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('não pode ser vazio');

        ScaffoldConfigValidator::validateAggregateOf('', 'Finance');
    }

    public function test_validate_aggregate_of_rejects_invalid_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('caracteres inválidos');

        ScaffoldConfigValidator::validateAggregateOf('invalid-name', 'FinanceHistory');
    }

    public function test_validate_aggregate_of_rejects_same_as_domain(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('não pode ser agregado de si mesmo');

        ScaffoldConfigValidator::validateAggregateOf('Finance', 'Finance');
    }

    public function test_validate_aggregate_of_accepts_valid(): void
    {
        ScaffoldConfigValidator::validateAggregateOf('Finance', 'FinanceHistory');
        $this->assertTrue(true);
    }

    // ── Full validate ──────────────────────────────────────────────────

    public function test_validate_passes_with_valid_inputs(): void
    {
        ScaffoldConfigValidator::validate('Financial', 'Finance', ['create', 'list']);
        $this->assertTrue(true);
    }

    public function test_validate_with_aggregate_of(): void
    {
        ScaffoldConfigValidator::validate('Financial', 'FinanceHistory', ['create'], 'Finance');
        $this->assertTrue(true);
    }

    public function test_validate_fails_on_empty_module(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pasta base do módulo é obrigatória.');

        ScaffoldConfigValidator::validate('', 'Finance', ['create']);
    }

    public function test_validate_fails_on_empty_domain(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nome do domínio é obrigatório.');

        ScaffoldConfigValidator::validate('Financial', '', ['create']);
    }

    public function test_validate_fails_on_invalid_features(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ScaffoldConfigValidator::validate('Financial', 'Finance', ['invalid']);
    }

    public function test_validate_error_messages_in_portuguese(): void
    {
        try {
            ScaffoldConfigValidator::validateModule('');
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('obrigatória', $e->getMessage());
        }

        try {
            ScaffoldConfigValidator::validateDomain('');
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('obrigatório', $e->getMessage());
        }

        try {
            ScaffoldConfigValidator::validateFeatures([]);
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('necessário', $e->getMessage());
        }
    }
}
