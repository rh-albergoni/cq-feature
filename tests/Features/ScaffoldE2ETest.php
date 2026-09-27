<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Features;

use Cq\CqFeature\Services\ScaffoldConfig;
use Cq\CqFeature\Tests\Features\Support\TempFilesystemTestCase;

/**
 * E2E: criar um domínio do zero com CRUD completo (RF-07, RN-03).
 *
 * Porta o `ScaffoldE2ETest` do FeatureMaker do `cq/api` para a suíte
 * `Features` em PHPUnit puro, exercitando {@see ScaffoldService::run()} sobre o
 * filesystem temporário e validando a árvore de arquivos (Shared + 5 features +
 * Factory + Migration + TestCase + Tests), os namespaces gerados (config
 * default ⇒ `App\Modules\...`), ausência de placeholders residuais, sintaxe PHP
 * válida e a geração opcional de rotas (`--register-routes`).
 */
final class ScaffoldE2ETest extends TempFilesystemTestCase
{
    private const MODULE = 'Financial';

    private const DOMAIN = 'FinanceE2E';

    public function test_full_crud_creates_domain_directory(): void
    {
        $this->runCrud();

        $this->assertDirectoryExists($this->appPath.'/Modules/Financial/FinanceE2E');
    }

    public function test_full_crud_creates_shared_structure(): void
    {
        $resolver = $this->runCrud();

        foreach ($resolver->sharedFiles() as $identifier => $path) {
            $this->assertFileExists($path, "Shared [{$identifier}] deveria existir em {$path}");
        }
    }

    public function test_full_crud_creates_all_five_feature_directories(): void
    {
        $this->runCrud();

        $domainPath = $this->appPath.'/Modules/Financial/FinanceE2E';

        foreach (['Create', 'Delete', 'Update', 'List', 'Find'] as $action) {
            $this->assertDirectoryExists(
                $domainPath.'/Features/FinanceE2E'.$action,
                "Feature FinanceE2E{$action} deveria existir",
            );
        }
    }

    public function test_full_crud_creates_all_feature_files(): void
    {
        $resolver = $this->runCrud();

        foreach (['create', 'delete', 'update', 'list', 'find'] as $feature) {
            foreach ($resolver->featureFiles($feature) as $identifier => $path) {
                $this->assertFileExists($path, "Feature [{$feature}] arquivo [{$identifier}] deveria existir em {$path}");
            }
        }
    }

    public function test_full_crud_creates_factory_testcase_and_feature_tests(): void
    {
        $resolver = $this->runCrud();

        $this->assertFileExists($resolver->factoryPath());
        $this->assertFileExists($resolver->testCasePath());

        foreach (['create', 'delete', 'update', 'list', 'find'] as $feature) {
            $this->assertFileExists($resolver->featureTestPath($feature));
        }
    }

    public function test_full_crud_creates_migration(): void
    {
        $resolver = $this->runCrud();

        $migrations = glob($this->tmp.'/database/migrations/*_create_finance_e2_es_table.php') ?: [];
        $this->assertCount(1, $migrations, 'Deveria existir exatamente uma migration para crud');
    }

    public function test_entity_contains_correct_namespace(): void
    {
        $resolver = $this->runCrud();

        $content = (string) file_get_contents($resolver->sharedFiles()['Entity']);

        $this->assertStringContainsString('App\\Modules\\Financial\\FinanceE2E', $content);
        $this->assertStringContainsString('class FinanceE2EEntity', $content);
    }

    public function test_controller_contains_correct_namespace(): void
    {
        $resolver = $this->runCrud();

        $content = (string) file_get_contents($resolver->featureFiles('create')['Controller']);

        $this->assertStringContainsString(
            'namespace App\\Modules\\Financial\\FinanceE2E\\Features\\FinanceE2ECreate\\Controllers;',
            $content,
        );
        $this->assertStringContainsString('class FinanceE2ECreateController', $content);
    }

    public function test_all_generated_files_have_no_residual_placeholders(): void
    {
        $result = $this->makeService($this->makePathResolver(self::MODULE, self::DOMAIN))
            ->run(ScaffoldConfig::make(module: self::MODULE, domain: self::DOMAIN, features: ['crud']));

        foreach ($result->getCreated() as $path) {
            $content = (string) file_get_contents($path);
            $this->assertStringNotContainsString('{{', $content, "Arquivo {$path} contém placeholders residuais");
        }
    }

    public function test_all_generated_php_files_are_valid_syntax(): void
    {
        $result = $this->makeService($this->makePathResolver(self::MODULE, self::DOMAIN))
            ->run(ScaffoldConfig::make(module: self::MODULE, domain: self::DOMAIN, features: ['crud']));

        foreach ($result->getCreated() as $path) {
            if (! str_ends_with($path, '.php')) {
                continue;
            }

            $output = [];
            $exitCode = 0;
            exec('php -l '.escapeshellarg($path).' 2>&1', $output, $exitCode);

            $this->assertSame(0, $exitCode, "Arquivo {$path} com erro de sintaxe PHP: ".implode("\n", $output));
        }
    }

    public function test_full_crud_with_register_routes(): void
    {
        $config = ScaffoldConfig::make(
            module: self::MODULE,
            domain: self::DOMAIN,
            features: ['crud'],
            registerRoutes: true,
        );

        $this->makeService($this->makePathResolver(self::MODULE, self::DOMAIN), registerRoutes: true)->run($config);

        $routePath = $this->tmp.'/routes/api/modules/financial/finance-e2-es.php';
        $this->assertFileExists($routePath, 'Arquivo de rotas deveria ser criado com --register-routes');

        $content = (string) file_get_contents($routePath);
        $this->assertStringContainsString('Route::post(', $content);
        $this->assertStringContainsString('Route::get(', $content);
        $this->assertStringContainsString('Route::put(', $content);
        $this->assertStringContainsString('Route::delete(', $content);

        $output = [];
        $exitCode = 0;
        exec('php -l '.escapeshellarg($routePath).' 2>&1', $output, $exitCode);
        $this->assertSame(0, $exitCode, 'Arquivo de rotas deveria ser PHP válido');
    }

    public function test_does_not_write_outside_temp_filesystem(): void
    {
        $this->runCrud();

        $this->assertDirectoryDoesNotExist(__DIR__.'/Modules');
    }

    /**
     * Executa o CRUD completo e devolve o resolver para inspeção dos paths.
     */
    private function runCrud(): \Cq\CqFeature\Services\PathResolver
    {
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN);
        $config = ScaffoldConfig::make(module: self::MODULE, domain: self::DOMAIN, features: ['crud']);

        $result = $this->makeService($resolver)->run($config);
        $this->assertFalse($result->hasErrors(), implode("\n", $result->getErrors()));

        return $resolver;
    }
}
