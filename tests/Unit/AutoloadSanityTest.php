<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Cq\CqFeature\Laravel\CqFeatureServiceProvider;
use Cq\CqFeature\Tests\Support\AutoloadProbe;

/**
 * Sanidade do autoload PSR-4 do pacote.
 *
 * Garante que o namespace base (`Cq\CqFeature\` -> `src/`) e o namespace de
 * testes (`Cq\CqFeature\Tests\` -> `tests/`) são resolvidos pelo Composer
 * sem `require` manual.
 */
final class AutoloadSanityTest extends TestCase
{
    public function test_resolve_namespace_base_do_pacote(): void
    {
        self::assertTrue(
            class_exists(CqFeatureServiceProvider::class),
            'O namespace base Cq\\CqFeature\\ deve resolver para src/ via autoload PSR-4.'
        );
    }

    public function test_classe_do_pacote_pertence_ao_namespace_esperado(): void
    {
        self::assertSame(
            'Cq\\CqFeature\\Laravel',
            (new \ReflectionClass(CqFeatureServiceProvider::class))->getNamespaceName(),
        );
    }

    public function test_resolve_namespace_de_testes_via_autoload_dev(): void
    {
        self::assertTrue(
            class_exists(AutoloadProbe::class),
            'O namespace Cq\\CqFeature\\Tests\\ deve resolver para tests/ via autoload-dev.'
        );

        self::assertSame('cqfeature', AutoloadProbe::ping());
    }
}
