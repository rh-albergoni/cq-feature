<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceDelete\Repositories\Commands;

use App\Modules\Financial\Finance\Shared\Repositories\Commands\FinanceCommandRepository;

class FinanceDeleteCommandRepository extends FinanceCommandRepository
{
    /**
     * Deletar item(ns)
     */
    public function execute(int|array $ids): bool
    {
        $this->ensureCallerIsAuthorized();

        return $this->delete($ids);
    }

    /**
     * @return array<class-string>
     */
    protected function authorizedCallers(): array
    {
        return [
            'App\Modules\Financial\Finance\Features\FinanceDelete\Services\FinanceDeleteService',
        ];
    }
}
