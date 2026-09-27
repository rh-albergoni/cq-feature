<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceCreate\Services;

use App\Modules\Financial\Finance\Features\FinanceCreate\Dtos\FinanceCreateDto;
use App\Modules\Financial\Finance\Features\FinanceCreate\Repositories\Commands\FinanceCreateCommandRepository;
use App\Modules\Financial\Finance\Features\FinanceFind\Dtos\FinanceViewDto;
use App\Modules\Financial\Finance\Features\FinanceFind\Services\FinanceFindService;
use App\Modules\Financial\Finance\Shared\Entities\FinanceEntity;
use App\Shared\Services\BaseService;

final class FinanceCreateService extends BaseService
{
    public function __construct(
        private readonly FinanceCreateCommandRepository $commandRepository,
        private readonly FinanceFindService $financeFindService,
    ) {
        parent::__construct();
    }

    public function execute(FinanceCreateDto $data): FinanceViewDto
    {
        return $this->handleTransaction(function () use ($data) {
            $entity = FinanceEntity::from($data);
            $createdId = $this->commandRepository->execute($entity);

            return $this->financeFindService->execute($createdId);
        });
    }
}
