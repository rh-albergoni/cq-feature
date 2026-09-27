<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceUpdate\Services;

use App\Modules\Financial\Finance\Features\FinanceFind\Dtos\FinanceViewDto;
use App\Modules\Financial\Finance\Features\FinanceFind\Services\FinanceFindService;
use App\Modules\Financial\Finance\Features\FinanceUpdate\Dtos\FinanceUpdateDto;
use App\Modules\Financial\Finance\Features\FinanceUpdate\Repositories\Commands\FinanceUpdateCommandRepository;
use App\Modules\Financial\Finance\Shared\Entities\FinanceEntity;
use App\Shared\Services\BaseService;

final class FinanceUpdateService extends BaseService
{
    public function __construct(
        private readonly FinanceUpdateCommandRepository $commandRepository,
        private readonly FinanceFindService $financeFindService,
    ) {
        parent::__construct();
    }

    public function execute(FinanceUpdateDto $data, int $id): FinanceViewDto
    {
        return $this->handleTransaction(function () use ($data, $id) {
            $entity = FinanceEntity::from($data);
            $this->commandRepository->execute($entity, $id);

            return $this->financeFindService->execute($id);
        });
    }
}
