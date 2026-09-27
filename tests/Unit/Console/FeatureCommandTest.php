<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Unit\Console;

use Illuminate\Console\Command;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Cq\CqFeature\Config\CqFeatureConfig;
use Cq\CqFeature\Console\FeatureCommand;

/**
 * Testes do {@see FeatureCommand} focados no parsing da `signature` dinâmica e na
 * preservação de argumentos/opções, sem Orchestra Testbench.
 *
 * A `signature` é montada no construtor a partir do {@see CqFeatureConfig}; o
 * próprio construtor de {@see Command} faz o parse (Illuminate `Parser`), então
 * é possível inspecionar nome/definição sem subir o framework. Um caso resolve o
 * comando via `Illuminate\Container\Container` puro (autowiring do VO injetado).
 */
final class FeatureCommandTest extends TestCase
{
    private function command(array $config = []): FeatureCommand
    {
        return new FeatureCommand(CqFeatureConfig::fromArray($config));
    }

    public function test_default_signature_uses_feature_name(): void
    {
        self::assertSame('feature', $this->command()->getName());
    }

    public function test_custom_command_name_changes_registered_signature(): void
    {
        $command = $this->command(['command' => ['name' => 'cq:scaffold']]);

        self::assertSame('cq:scaffold', $command->getName());
    }

    public function test_arguments_are_preserved_and_optional(): void
    {
        $definition = $this->command()->getDefinition();

        self::assertTrue($definition->hasArgument('module'));
        self::assertTrue($definition->hasArgument('domain'));

        self::assertFalse($definition->getArgument('module')->isRequired());
        self::assertFalse($definition->getArgument('domain')->isRequired());
    }

    public function test_all_options_are_preserved(): void
    {
        $definition = $this->command()->getDefinition();

        self::assertTrue($definition->hasOption('features'));
        self::assertTrue($definition->hasOption('force'));
        self::assertTrue($definition->hasOption('register-routes'));
        self::assertTrue($definition->hasOption('aggregate-of'));
    }

    public function test_value_options_require_value_and_flags_do_not(): void
    {
        $definition = $this->command()->getDefinition();

        // --features e --aggregate-of recebem valor (VALUE_OPTIONAL via signature `=`)
        self::assertTrue($definition->getOption('features')->acceptValue());
        self::assertTrue($definition->getOption('aggregate-of')->acceptValue());

        // --force e --register-routes são flags (VALUE_NONE)
        self::assertFalse($definition->getOption('force')->acceptValue());
        self::assertFalse($definition->getOption('register-routes')->acceptValue());
    }

    public function test_custom_name_preserves_all_arguments_and_options(): void
    {
        $definition = $this->command(['command' => ['name' => 'cq:scaffold']])->getDefinition();

        self::assertTrue($definition->hasArgument('module'));
        self::assertTrue($definition->hasArgument('domain'));
        self::assertTrue($definition->hasOption('features'));
        self::assertTrue($definition->hasOption('force'));
        self::assertTrue($definition->hasOption('register-routes'));
        self::assertTrue($definition->hasOption('aggregate-of'));
    }

    public function test_description_is_preserved(): void
    {
        self::assertSame(
            'Gera a estrutura de módulo e features (Shared + Create, Delete, Update, List, Find ou customizadas).',
            $this->command()->getDescription(),
        );
    }

    public function test_is_instance_of_illuminate_command(): void
    {
        self::assertInstanceOf(Command::class, $this->command());
    }

    public function test_command_is_resolvable_through_pure_container(): void
    {
        $container = new Container();
        $container->instance(
            CqFeatureConfig::class,
            CqFeatureConfig::fromArray(['command' => ['name' => 'cq:scaffold']]),
        );

        $command = $container->make(FeatureCommand::class);

        self::assertInstanceOf(FeatureCommand::class, $command);
        self::assertSame('cq:scaffold', $command->getName());
    }
}
