<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Features;

use Cq\CqFeature\Services\ScaffoldConfig;
use Cq\CqFeature\Tests\Features\Support\TempFilesystemTestCase;

/**
 * E2E: scaffold de feature customizada (PascalCase).
 *
 * Porta a parte de geração do `CustomFeatureTest`: uma feature customizada gera
 * o conjunto completo de 9 artefatos (Controller, Service, Request, Dto,
 * Command/Query Repositories, Command/Query Dao, README) nas subpastas corretas
 * e com os namespaces corretos, podendo conviver com features padrão (CRUD).
 */
final class CustomFeatureTest extends TempFilesystemTestCase
{
    private const MODULE = 'Financial';

    private const DOMAIN = 'Finance';

    private const CUSTOM = 'ChangeInstallmentStatus';

    public function test_custom_feature_generates_all_nine_artifacts(): void
    {
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN);
        $result = $this->makeService($resolver)->run(
            ScaffoldConfig::make(module: self::MODULE, domain: self::DOMAIN, features: [self::CUSTOM]),
        );
        $this->assertFalse($result->hasErrors(), implode("\n", $result->getErrors()));

        $files = $resolver->featureFiles(self::CUSTOM);
        $this->assertCount(9, $files);
        foreach ($files as $identifier => $path) {
            $this->assertFileExists($path, "Artefato custom [{$identifier}] deveria existir em {$path}");
        }
    }

    public function test_custom_feature_files_are_in_correct_subfolders(): void
    {
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN);
        $this->makeService($resolver)->run(
            ScaffoldConfig::make(module: self::MODULE, domain: self::DOMAIN, features: [self::CUSTOM]),
        );

        $featurePath = $resolver->featurePath(self::CUSTOM);
        $this->assertFileExists($featurePath.'/Controllers/FinanceChangeInstallmentStatusController.php');
        $this->assertFileExists($featurePath.'/Services/FinanceChangeInstallmentStatusService.php');
        $this->assertFileExists($featurePath.'/Requests/FinanceChangeInstallmentStatusRequest.php');
        $this->assertFileExists($featurePath.'/Dtos/FinanceChangeInstallmentStatusDto.php');
        $this->assertFileExists($featurePath.'/Repositories/Commands/FinanceChangeInstallmentStatusCommandRepository.php');
        $this->assertFileExists($featurePath.'/Repositories/Queries/FinanceChangeInstallmentStatusQueryRepository.php');
        $this->assertFileExists($featurePath.'/Dao/Commands/FinanceChangeInstallmentStatusCommandDao.php');
        $this->assertFileExists($featurePath.'/Dao/Queries/FinanceChangeInstallmentStatusQueryDao.php');
        $this->assertFileExists($featurePath.'/README.md');
    }

    public function test_custom_feature_controller_has_correct_namespace(): void
    {
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN);
        $this->makeService($resolver)->run(
            ScaffoldConfig::make(module: self::MODULE, domain: self::DOMAIN, features: [self::CUSTOM]),
        );

        $content = (string) file_get_contents($resolver->featureFiles(self::CUSTOM)['Controller']);
        $this->assertStringContainsString(
            'namespace App\\Modules\\Financial\\Finance\\Features\\FinanceChangeInstallmentStatus\\Controllers;',
            $content,
        );
        $this->assertStringContainsString('class FinanceChangeInstallmentStatusController', $content);
        $this->assertStringNotContainsString('{{', $content);
    }

    public function test_crud_with_custom_feature_generates_both(): void
    {
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN);
        $result = $this->makeService($resolver)->run(
            ScaffoldConfig::make(module: self::MODULE, domain: self::DOMAIN, features: ['crud', self::CUSTOM]),
        );
        $this->assertFalse($result->hasErrors(), implode("\n", $result->getErrors()));

        foreach (['create', 'delete', 'update', 'list', 'find'] as $feature) {
            foreach ($resolver->featureFiles($feature) as $path) {
                $this->assertFileExists($path);
            }
        }
        foreach ($resolver->featureFiles(self::CUSTOM) as $path) {
            $this->assertFileExists($path);
        }
    }

    public function test_custom_feature_register_routes_uses_kebab_action(): void
    {
        $config = ScaffoldConfig::make(
            module: self::MODULE,
            domain: self::DOMAIN,
            features: [self::CUSTOM],
            registerRoutes: true,
        );

        $this->makeService($this->makePathResolver(self::MODULE, self::DOMAIN), registerRoutes: true)->run($config);

        $routePath = $this->tmp.'/routes/api/modules/financial/finances.php';
        $this->assertFileExists($routePath);

        $content = (string) file_get_contents($routePath);
        $this->assertStringContainsString("Route::post('finances/change-installment-status'", $content);
    }
}
