<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Unit\Laravel\Fixtures;

use Illuminate\Container\Container;

/**
 * Stub mínimo do `Application` Laravel para exercitar o
 * {@see \Cq\CqFeature\Laravel\CqFeatureServiceProvider} sem subir o framework
 * completo (PHPUnit puro, sem Orchestra Testbench).
 *
 * Implementa apenas o que o provider realmente chama: `runningInConsole()` e
 * `basePath()`.
 */
final class AppStub extends Container
{
    private bool $console;

    public function __construct(bool $runningInConsole = true)
    {
        $this->console = $runningInConsole;
    }

    public function runningInConsole(): bool
    {
        return $this->console;
    }

    public function basePath(string $path = ''): string
    {
        return sys_get_temp_dir().'/cqfeature-test/'.ltrim($path, '/');
    }
}
