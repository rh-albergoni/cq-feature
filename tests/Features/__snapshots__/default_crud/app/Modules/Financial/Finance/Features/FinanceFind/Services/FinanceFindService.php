<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceFind\Services;

use App\Modules\Financial\Finance\Features\FinanceFind\Dao\Queries\FinanceFindQueryDao;
use App\Modules\Financial\Finance\Features\FinanceFind\Dtos\FinanceViewDto;
use App\Shared\Exceptions\DefaultException;
use App\Shared\Services\BaseService;

final class FinanceFindService extends BaseService
{
    public function __construct(
        private readonly FinanceFindQueryDao $queryDao,
    ) {
        parent::__construct();
    }

    public function execute(int $id): FinanceViewDto
    {
        $viewDto = $this->queryDao->execute($id);
        if (! $viewDto) {
            throw new DefaultException;
        }

        return $viewDto;
    }
}
