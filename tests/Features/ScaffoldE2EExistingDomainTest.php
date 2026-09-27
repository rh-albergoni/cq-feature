<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Features;

use Cq\CqFeature\Services\ScaffoldConfig;
use Cq\CqFeature\Tests\Features\Support\TempFilesystemTestCase;

/**
 * E2E: adicionar feature em domínio existente sem sobrescrita (RN-03).
 *
 * Porta o `ScaffoldE2EExistingDomainTest` do FeatureMaker: simula o ciclo real
 * (gera, o desenvolvedor edita, roda de novo) garantindo que arquivos
 * existentes são SKIPados (nunca sobrescritos), Shared não é recriado para
 * domínio existente e novas features são geradas com os namespaces corretos.
 */
final class ScaffoldE2EExistingDomainTest extends TempFilesystemTestCase
{
    private const MODULE = 'Financial';

    private const DOMAIN = 'FinanceE2E';

    public function test_modified_file_is_preserved_after_second_run(): void
    {
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN);
        $this->makeService($resolver)->run($this->config(['create']));

        $controllerPath = $resolver->featureFiles('create')['Controller'];
        $this->assertFileExists($controllerPath);
        file_put_contents($controllerPath, (string) file_get_contents($controllerPath)."\n// E2E_MARKER");

        $this->makeService($resolver)->run($this->config(['create', 'list']));

        $this->assertStringContainsString(
            '// E2E_MARKER',
            (string) file_get_contents($controllerPath),
            'O arquivo modificado NÃO deveria ter sido sobrescrito',
        );
    }

    public function test_new_feature_is_created_alongside_existing(): void
    {
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN);
        $service = $this->makeService($resolver);

        $service->run($this->config(['create']));
        $result = $service->run($this->config(['create', 'list']));

        $this->assertFalse($result->isNewDomain());

        foreach ($resolver->featureFiles('list') as $identifier => $path) {
            $this->assertFileExists($path, "Feature list arquivo [{$identifier}] deveria existir em {$path}");
        }
        foreach ($resolver->featureFiles('create') as $identifier => $path) {
            $this->assertFileExists($path, "Feature create arquivo [{$identifier}] deveria existir em {$path}");
        }
    }

    public function test_second_run_reports_skipped_existing_files(): void
    {
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN);
        $service = $this->makeService($resolver);

        $service->run($this->config(['create']));
        $result = $service->run($this->config(['create', 'list']));

        $createController = $resolver->featureFiles('create')['Controller'];
        $this->assertContains($createController, $result->getSkipped());
        $this->assertGreaterThan(0, $result->skippedCount());
    }

    public function test_shared_is_not_recreated_on_existing_domain(): void
    {
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN);
        $service = $this->makeService($resolver);

        $service->run($this->config(['create']));

        $entityPath = $resolver->sharedFiles()['Entity'];
        file_put_contents($entityPath, (string) file_get_contents($entityPath)."\n// SHARED_MARKER");

        $result = $service->run($this->config(['list']));

        $this->assertFalse($result->isNewDomain());
        foreach ($resolver->sharedFiles() as $path) {
            $this->assertNotContains($path, $result->getCreated());
        }
        $this->assertStringContainsString(
            '// SHARED_MARKER',
            (string) file_get_contents($entityPath),
            'Shared Entity deveria manter a alteração do desenvolvedor',
        );
    }

    public function test_all_create_feature_files_preserved_with_marker(): void
    {
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN);
        $service = $this->makeService($resolver);

        $service->run($this->config(['create']));

        $createFiles = $resolver->featureFiles('create');
        foreach ($createFiles as $identifier => $path) {
            file_put_contents($path, (string) file_get_contents($path)."\n// MARKER_{$identifier}");
        }

        $service->run($this->config(['create', 'list']));

        foreach ($createFiles as $identifier => $path) {
            $this->assertStringContainsString(
                "// MARKER_{$identifier}",
                (string) file_get_contents($path),
                "Arquivo [{$identifier}] da feature create deveria manter o marcador",
            );
        }
    }

    public function test_new_list_files_have_correct_namespace(): void
    {
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN);
        $service = $this->makeService($resolver);

        $service->run($this->config(['create']));
        $service->run($this->config(['list']));

        $content = (string) file_get_contents($resolver->featureFiles('list')['Controller']);

        $this->assertStringContainsString(
            'namespace App\\Modules\\Financial\\FinanceE2E\\Features\\FinanceE2EList\\Controllers;',
            $content,
        );
        $this->assertStringContainsString('class FinanceE2EListController', $content);
    }

    public function test_full_crud_then_re_add_single_feature_creates_nothing(): void
    {
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN);
        $service = $this->makeService($resolver);

        $service->run($this->config(['crud']));

        $updateController = $resolver->featureFiles('update')['Controller'];
        file_put_contents($updateController, (string) file_get_contents($updateController)."\n// DEVELOPER_CUSTOM_CODE");

        $result = $service->run($this->config(['update']));

        $this->assertSame(0, $result->createdCount());
        $this->assertStringContainsString('// DEVELOPER_CUSTOM_CODE', (string) file_get_contents($updateController));
    }

    /**
     * @param  array<int, string>  $features
     */
    private function config(array $features): ScaffoldConfig
    {
        return ScaffoldConfig::make(module: self::MODULE, domain: self::DOMAIN, features: $features);
    }
}
