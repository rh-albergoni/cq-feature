<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceFind\Repositories\Queries;

use App\Modules\Financial\Finance\Shared\Entities\FinanceEntity;
use App\Modules\Financial\Finance\Shared\Repositories\Queries\FinanceQueryRepository;

class FinanceFindQueryRepository extends FinanceQueryRepository
{
    /**
     * Localizar registro por ID
     */
    public function execute(int $id): ?FinanceEntity
    {
        $this->ensureCallerIsAuthorized();

        $model = $this->baseQuery()
            ->where('id', $id)
            ->first();

        if (! $model) {
            return null;
        }

        return $this->modelToEntity($model);
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
