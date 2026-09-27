<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Features;

use Cq\CqFeature\Services\PathResolver;
use Cq\CqFeature\Services\ScaffoldConfig;
use Cq\CqFeature\Tests\Features\Support\TempFilesystemTestCase;

/**
 * Teste de EQUIVALÊNCIA por snapshot — guardião da regra de ouro (RN-01).
 *
 * Com a config default, a saída do CqFeature deve ser IDÊNTICA à do FeatureMaker
 * do `cq/api`. O snapshot de referência em {@see __snapshots__/default_crud}
 * foi gerado executando o FeatureMaker original (domínio Financial/Finance,
 * `--features=crud --register-routes`), normalizando apenas o timestamp do nome
 * da migration. Este teste regenera com o CqFeature e compara árvore + conteúdo
 * byte-a-byte.
 *
 * Para atualizar o snapshot, veja a nota "Atualizando snapshots" no README.
 *
 * @see tests/Features/__snapshots__/default_crud
 */
final class EquivalenceSnapshotTest extends TempFilesystemTestCase
{
    private const MODULE = 'Financial';

    private const DOMAIN = 'Finance';

    /** Token estável que substitui o timestamp da migration na comparação. */
    private const MIGRATION_TS = '0000_00_00_000000';

    private function snapshotDir(): string
    {
        return __DIR__.'/__snapshots__/default_crud';
    }

    public function test_snapshot_directory_exists_and_is_not_empty(): void
    {
        $this->assertDirectoryExists($this->snapshotDir(), 'Snapshot de referência ausente');
        $this->assertNotEmpty($this->listTree($this->snapshotDir()), 'Snapshot de referência vazio');
    }

    public function test_default_config_output_is_identical_to_featuremaker_snapshot(): void
    {
        $generated = $this->generateDefaultCrud();

        $expectedTree = $this->listTree($this->snapshotDir());

        // 1) Árvore de arquivos idêntica (mesmos caminhos relativos).
        $this->assertSame(
            $expectedTree,
            array_keys($generated),
            'A árvore de arquivos gerada difere do snapshot do FeatureMaker',
        );

        // 2) Conteúdo idêntico byte-a-byte para cada arquivo.
        foreach ($expectedTree as $relative) {
            $expectedContent = (string) file_get_contents($this->snapshotDir().'/'.$relative);
            $actualContent = (string) file_get_contents($generated[$relative]);

            $this->assertSame(
                $expectedContent,
                $actualContent,
                "Conteúdo divergente em relação ao FeatureMaker no arquivo: {$relative}",
            );
        }
    }

    public function test_snapshot_covers_shared_features_tests_factory_migration_and_routes(): void
    {
        $tree = $this->listTree($this->snapshotDir());

        $assertHasMatch = function (string $needle) use ($tree): void {
            $found = array_filter($tree, static fn (string $p): bool => str_contains($p, $needle));
            $this->assertNotEmpty($found, "Snapshot deveria cobrir: {$needle}");
        };

        $assertHasMatch('app/Modules/Financial/Finance/Shared/Entities/FinanceEntity.php');
        $assertHasMatch('app/Modules/Financial/Finance/Features/FinanceCreate/Controllers');
        $assertHasMatch('tests/Feature/Modules/Financial/Finance/Shared/FinanceTestCase.php');
        $assertHasMatch('database/factories/Modules/Financial/Finance/Shared/Models/FinanceFactory.php');
        $assertHasMatch('database/migrations/'.self::MIGRATION_TS.'_create_finances_table.php');
        $assertHasMatch('routes/api/modules/financial/finances.php');
    }

    public function test_existing_domain_second_run_skips_everything_like_featuremaker(): void
    {
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN);
        $service = $this->makeService($resolver);

        $config = ScaffoldConfig::make(module: self::MODULE, domain: self::DOMAIN, features: ['crud']);

        $first = $service->run($config);
        $this->assertTrue($first->isNewDomain());
        $this->assertGreaterThan(0, $first->createdCount());

        // Segundo run (domínio existente): nada é criado, plano inteiro é SKIPado.
        $second = $service->run($config);
        $this->assertFalse($second->isNewDomain());
        $this->assertSame(0, $second->createdCount());

        // TestCase + arquivos e testes de cada feature CRUD devem aparecer como skipped.
        $this->assertContains($resolver->testCasePath(), $second->getSkipped());
        foreach (['create', 'delete', 'update', 'list', 'find'] as $feature) {
            foreach ($resolver->featureFiles($feature) as $path) {
                $this->assertContains($path, $second->getSkipped());
            }
            $this->assertContains($resolver->featureTestPath($feature), $second->getSkipped());
        }
    }

    public function test_existing_domain_add_feature_creates_only_new_feature_no_shared(): void
    {
        $resolver = $this->makePathResolver(self::MODULE, self::DOMAIN);
        $service = $this->makeService($resolver);

        $service->run(ScaffoldConfig::make(module: self::MODULE, domain: self::DOMAIN, features: ['create']));
        $result = $service->run(ScaffoldConfig::make(module: self::MODULE, domain: self::DOMAIN, features: ['list']));

        $this->assertFalse($result->isNewDomain());

        // Shared não é recriado em domínio existente (igual ao FeatureMaker).
        foreach ($resolver->sharedFiles() as $path) {
            $this->assertNotContains($path, $result->getCreated());
        }
        // Apenas os arquivos da nova feature são criados.
        foreach ($resolver->featureFiles('list') as $path) {
            $this->assertContains($path, $result->getCreated());
        }
    }

    /**
     * Gera o domínio default (crud + rotas) no filesystem temporário e devolve o
     * mapa [caminho relativo normalizado => caminho absoluto].
     *
     * @return array<string, string>
     */
    private function generateDefaultCrud(): array
    {
        $resolver = new PathResolver($this->makeConfig(), self::MODULE, self::DOMAIN, $this->appPath);

        $config = ScaffoldConfig::make(
            module: self::MODULE,
            domain: self::DOMAIN,
            features: ['crud'],
            registerRoutes: true,
            isNewDomain: true,
        );

        $result = $this->makeService($resolver, registerRoutes: true)->run($config);
        $this->assertFalse($result->hasErrors(), implode("\n", $result->getErrors()));

        $map = [];
        foreach ($this->listTree($this->tmp) as $relative) {
            $normalized = $this->normalizeMigrationPath($relative);
            $map[$normalized] = $this->tmp.'/'.$relative;
        }

        ksort($map);

        return $map;
    }

    /**
     * Normaliza o timestamp do nome da migration para um token estável,
     * permitindo comparação determinística com o snapshot.
     */
    private function normalizeMigrationPath(string $relative): string
    {
        return (string) preg_replace(
            '#(database/migrations/)\d{4}_\d{2}_\d{2}_\d{6}(_create_)#',
            '${1}'.self::MIGRATION_TS.'${2}',
            $relative,
        );
    }
}
