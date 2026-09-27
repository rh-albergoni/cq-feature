<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceCreate\Repositories\Commands;

use App\Modules\Financial\Finance\Shared\Repositories\Commands\FinanceCommandRepository;
use App\Modules\Financial\Finance\Shared\Entities\FinanceEntity;
use App\Shared\Entities\BaseEntity;

class FinanceCreateCommandRepository extends FinanceCommandRepository
{
    /**
     * Criar registro
     *
     * @param  FinanceEntity  $data
     */
    public function execute(BaseEntity|FinanceEntity $data): int
    {
        $this->ensureCallerIsAuthorized();

        $model = $this->create($data);

        return $model->id;
    }

    /**
     * @return array<class-string>
     */
    protected function authorizedCallers(): array
    {
        return [
            'App\Modules\Financial\Finance\Features\FinanceCreate\Services\FinanceCreateService',
        ];
    }
}
