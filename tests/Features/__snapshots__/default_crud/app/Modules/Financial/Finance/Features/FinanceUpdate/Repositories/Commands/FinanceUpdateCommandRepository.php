<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceUpdate\Repositories\Commands;

use App\Modules\Financial\Finance\Shared\Repositories\Commands\FinanceCommandRepository;
use App\Modules\Financial\Finance\Shared\Entities\FinanceEntity;
use App\Shared\Entities\BaseEntity;

class FinanceUpdateCommandRepository extends FinanceCommandRepository
{
    /**
     * Atualizar registro
     *
     * @param  FinanceEntity  $data
     * @param  int  $id
     */
    public function execute(BaseEntity|FinanceEntity $data, int $id): bool
    {
        $this->ensureCallerIsAuthorized();

        $this->update($data, $id);

        return true;
    }

    /**
     * @return array<class-string>
     */
    protected function authorizedCallers(): array
    {
        return [
            'App\Modules\Financial\Finance\Features\FinanceUpdate\Services\FinanceUpdateService',
        ];
    }
}
