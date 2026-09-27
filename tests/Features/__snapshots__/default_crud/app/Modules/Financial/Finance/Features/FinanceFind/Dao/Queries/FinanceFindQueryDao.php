<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceFind\Dao\Queries;

use App\Modules\Financial\Finance\Features\FinanceFind\Dtos\FinanceViewDto;
use App\Modules\Financial\Finance\Shared\Dao\Queries\FinanceQueryDao;

class FinanceFindQueryDao extends FinanceQueryDao
{
    /**
     * Localizar registro por ID
     */
    public function execute(int $id): ?FinanceViewDto
    {
        $this->ensureCallerIsAuthorized();

        $finance = $this->q()
            ->select([
                'finances.*',
            ])
            ->where('finances.id', $id)
            ->first();

        if (! $finance) {
            return null;
        }

        return $this->modelToViewDto($finance);
    }

    /**
     * @return array<class-string>
     */
    protected function authorizedCallers(): array
    {
        return [
            'App\Modules\Financial\Finance\Features\FinanceFind\Services\FinanceFindService',
        ];
    }
}
