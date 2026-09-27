<?php

declare(strict_types=1);

namespace Cq\CqFeature\Tests\Features\Support;

use PHPUnit\Framework\AssertionFailedError;

/**
 * Testes unitários do helper {@see TempFilesystemTestCase}: comparação de
 * árvore ({@see TempFilesystemTestCase::assertTreeEquals()}), listagem
 * ({@see TempFilesystemTestCase::listTree()}) e remoção recursiva
 * ({@see TempFilesystemTestCase::removeRecursive()}).
 *
 * Exercita a própria base de teste (concretizando-a) para garantir que a
 * infraestrutura dos E2E é confiável.
 */
final class TempFilesystemTestCaseTest extends TempFilesystemTestCase
{
    public function test_setup_creates_isolated_temp_filesystem(): void
    {
        $this->assertDirectoryExists($this->tmp);
        $this->assertDirectoryExists($this->appPath.'/Modules');
        $this->assertStringStartsWith(sys_get_temp_dir(), $this->tmp);
    }

    public function test_list_tree_returns_sorted_relative_paths(): void
    {
        mkdir($this->tmp.'/a/b', 0777, true);
        file_put_contents($this->tmp.'/a/b/second.txt', 'x');
        file_put_contents($this->tmp.'/a/first.txt', 'y');

        $tree = $this->listTree($this->tmp.'/a');

        // Ordenação determinística (sort lexicográfico).
        $this->assertSame(['b/second.txt', 'first.txt'], $tree);
    }

    public function test_list_tree_returns_empty_for_missing_directory(): void
    {
        $this->assertSame([], $this->listTree($this->tmp.'/does-not-exist'));
    }

    public function test_assert_tree_equals_passes_for_matching_tree(): void
    {
        mkdir($this->tmp.'/root/sub', 0777, true);
        file_put_contents($this->tmp.'/root/one.php', '1');
        file_put_contents($this->tmp.'/root/sub/two.php', '2');

        $this->assertTreeEquals(['one.php', 'sub/two.php'], $this->tmp.'/root');
    }

    public function test_assert_tree_equals_detects_difference_with_clear_diff(): void
    {
        mkdir($this->tmp.'/root', 0777, true);
        file_put_contents($this->tmp.'/root/present.php', '1');

        $caught = null;

        try {
            $this->assertTreeEquals(['expected.php'], $this->tmp.'/root');
        } catch (AssertionFailedError $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'assertTreeEquals deveria falhar para árvores divergentes');
        $this->assertStringContainsString('expected.php', $caught->getMessage());
        $this->assertStringContainsString('present.php', $caught->getMessage());
    }

    public function test_remove_recursive_removes_nested_structure(): void
    {
        $target = $this->tmp.'/nested';
        mkdir($target.'/deep/deeper', 0777, true);
        file_put_contents($target.'/deep/file.txt', 'a');
        file_put_contents($target.'/deep/deeper/file.txt', 'b');

        $this->assertDirectoryExists($target);

        $this->removeRecursive($target);

        $this->assertDirectoryDoesNotExist($target);
    }

    public function test_remove_recursive_is_noop_for_missing_directory(): void
    {
        $this->removeRecursive($this->tmp.'/never-existed');

        $this->assertDirectoryDoesNotExist($this->tmp.'/never-existed');
    }
}
