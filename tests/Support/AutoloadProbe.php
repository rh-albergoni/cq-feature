<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Support;

/**
 * Classe-sonda usada para verificar que o `autoload-dev`
 * (`Cq\CqFeature\Tests\` -> `tests/`) está corretamente configurado.
 */
final class AutoloadProbe
{
    public static function ping(): string
    {
        return 'cqfeature';
    }
}
