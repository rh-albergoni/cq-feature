<?php

declare(strict_types=1);

namespace Cq\CqFeature\Services;

/**
 * Resultado imutável da execução do scaffold.
 *
 * Contém listas de arquivos criados, ignorados (já existentes),
 * eventuais erros de I/O e indicador de domínio novo vs existente.
 *
 * Consumido pelo {@see \Cq\CqFeature\Console\FeatureCommand} (Task 7.0) para
 * montar o resumo (`createdCount`/`skippedCount`/`hasErrors`) e definir o exit
 * code do comando.
 */
final class ScaffoldResult
{
    /**
     * @param  array<string>  $created  Paths dos arquivos efetivamente criados
     * @param  array<string>  $skipped  Paths ignorados (já existiam)
     * @param  array<string>  $errors  Mensagens de erro de I/O
     * @param  bool  $isNewDomain  Se o domínio era novo (não existia antes do scaffold)
     */
    public function __construct(
        private readonly array $created = [],
        private readonly array $skipped = [],
        private readonly array $errors = [],
        private readonly bool $isNewDomain = true,
    ) {}

    /**
     * @return array<string>
     */
    public function getCreated(): array
    {
        return $this->created;
    }

    /**
     * @return array<string>
     */
    public function getSkipped(): array
    {
        return $this->skipped;
    }

    /**
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function createdCount(): int
    {
        return count($this->created);
    }

    public function skippedCount(): int
    {
        return count($this->skipped);
    }

    public function isNewDomain(): bool
    {
        return $this->isNewDomain;
    }
}
