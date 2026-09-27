<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceDelete\Services;

use App\Modules\Financial\Finance\Features\FinanceDelete\Repositories\Commands\FinanceDeleteCommandRepository;
use App\Shared\Exceptions\DefaultException;
use App\Shared\Services\BaseService;

final class FinanceDeleteService extends BaseService
{
    public function __construct(
        private readonly FinanceDeleteCommandRepository $commandRepository,
    ) {
        parent::__construct();
    }

    public function execute(int $id): void
    {
        $deleted = $this->commandRepository->execute($id);
        if (! $deleted) {
            throw new DefaultException;
        }
    }
}
