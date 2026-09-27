<?php

declare(strict_types=1);

namespace Cq\CqFeature\Services;

use Cq\CqFeature\Config\CqFeatureConfig;
use RuntimeException;

/**
 * Carrega stubs por nome e substitui placeholders.
 *
 * Stub names usam dot notation: "Shared.Entity" resolve para
 * "{stubDir}/Shared/Entity.php.stub". Placeholders usam o formato `{{Key}}` e
 * são substituídos pelos valores do array `$replace`.
 *
 * ZERO hardcode (RNF-03): o diretório dos stubs e o namespace raiz vêm do
 * {@see CqFeatureConfig} injetado. O placeholder `{{RootNamespace}}` é
 * preenchido automaticamente a partir de {@see CqFeatureConfig::rootNamespace()}
 * (default `App`), garantindo que com a config padrão a saída seja idêntica à do
 * FeatureMaker do `cq/api` (RN-01).
 *
 * Precedência de diretórios (RN-02): a resolução percorre, em ordem,
 * `[publishedStubsPath, packageStubsPath]` (descartando os nulos) — o stub
 * publicado no projeto vence o stub interno do pacote. Dentro de cada diretório
 * mantém-se o fallback de extensão `.php.stub` → `.md.stub`.
 *
 * Placeholders suportados (preenchidos pelo chamador, exceto onde indicado):
 *  - `{{RootNamespace}}` — injetado automaticamente da config (override pelo
 *    chamador é possível, mas desnecessário);
 *  - `{{Module}}`, `{{Domain}}`, `{{Action}}` — segmentos da árvore DDD;
 *  - demais chaves específicas de cada stub (ex.: `{{table_name}}`,
 *    `{{domain_snake}}`, `{{module_kebab}}`, etc.).
 *
 * @see \Cq\CqFeature\Services\PathResolver
 */
final class StubRenderer
{
    /**
     * Placeholder do namespace raiz, preenchido a partir da config.
     */
    private const ROOT_NAMESPACE_KEY = 'RootNamespace';

    /**
     * Diretórios de stubs em ordem de precedência (publicado, pacote).
     *
     * @var array<int, string>
     */
    private readonly array $stubDirs;

    /**
     * @param  CqFeatureConfig  $config  Configuração injetada (namespace raiz + caminhos de stubs).
     * @param  array<int, string>|null  $stubDirs  Diretórios em ordem de precedência
     *                                             [publicado, pacote]. Quando null,
     *                                             derivado da config.
     */
    public function __construct(
        private readonly CqFeatureConfig $config,
        ?array $stubDirs = null,
    ) {
        $this->stubDirs = array_values(array_filter(
            $stubDirs ?? [
                $this->config->publishedStubsPath(),
                $this->config->packageStubsPath(),
            ],
            static fn (?string $dir): bool => $dir !== null && $dir !== '',
        ));
    }

    /**
     * Renderiza um stub substituindo placeholders pelos valores informados.
     *
     * O `{{RootNamespace}}` é preenchido automaticamente da config; valores
     * passados em `$replace` têm precedência sobre o default da config.
     *
     * @param  string  $stubName  Nome do stub em dot notation (ex.: Shared.Entity)
     * @param  array<string, string>  $replace  Mapa de placeholders (ex.: ['Module' => 'Financial', 'Domain' => 'Finance'])
     *
     * @throws RuntimeException Se o stub não existir ou restar placeholder não substituído
     */
    public function render(string $stubName, array $replace): string
    {
        $path = $this->resolveStubPath($stubName);

        if ($path === null) {
            throw new RuntimeException(
                "Stub '{$stubName}' não encontrado em: ".$this->describeSearchedDirs()
            );
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException(
                "Não foi possível ler o stub '{$stubName}' em: {$path}"
            );
        }

        $replace = array_merge(
            [self::ROOT_NAMESPACE_KEY => $this->config->rootNamespace()],
            $replace,
        );

        $content = $this->replacePlaceholders($content, $replace);

        $this->ensureNoResidualPlaceholders($content, $stubName);

        return $content;
    }

    /**
     * Resolve o caminho do stub percorrendo os diretórios em ordem de
     * precedência. Em cada diretório aplica o fallback `.php.stub` → `.md.stub`.
     * Ex.: "Shared.Entity" => "{stubDir}/Shared/Entity.php.stub".
     *
     * @return string|null Caminho do primeiro arquivo existente ou null se nenhum encontrado.
     */
    private function resolveStubPath(string $stubName): ?string
    {
        $relativePath = str_replace('.', '/', $stubName);

        foreach ($this->stubDirs as $dir) {
            $phpStub = $dir.'/'.$relativePath.'.php.stub';
            if (file_exists($phpStub)) {
                return $phpStub;
            }

            $mdStub = $dir.'/'.$relativePath.'.md.stub';
            if (file_exists($mdStub)) {
                return $mdStub;
            }
        }

        return null;
    }

    /**
     * Substitui todos os placeholders {{Key}} pelos valores do array replace.
     *
     * @param  array<string, string>  $replace
     */
    private function replacePlaceholders(string $content, array $replace): string
    {
        foreach ($replace as $key => $value) {
            $content = str_replace('{{'.$key.'}}', $value, $content);
        }

        return $content;
    }

    /**
     * Garante que nenhum placeholder {{...}} ficou sem substituição.
     *
     * @throws RuntimeException Se houver placeholder residual
     */
    private function ensureNoResidualPlaceholders(string $content, string $stubName): void
    {
        if (preg_match('/\{\{(\w+)\}\}/', $content, $matches)) {
            throw new RuntimeException(
                "Placeholder residual '{{{$matches[1]}}}' encontrado no stub '{$stubName}'. "
                ."Verifique se todas as variáveis necessárias foram informadas no array replace."
            );
        }
    }

    /**
     * Descrição dos diretórios pesquisados, para mensagens de erro.
     */
    private function describeSearchedDirs(): string
    {
        return $this->stubDirs === [] ? '(nenhum diretório configurado)' : implode(', ', $this->stubDirs);
    }
}
