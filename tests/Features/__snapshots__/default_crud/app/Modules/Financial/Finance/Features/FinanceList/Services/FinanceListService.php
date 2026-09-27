<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceList\Services;

use App\Modules\Financial\Finance\Features\FinanceList\Dao\Queries\FinanceListQueryDao;
use App\Modules\Financial\Finance\Features\FinanceList\FilterDtos\FinanceListFilterDto;
use App\Shared\Dtos\PagerDto;
use App\Shared\Services\BaseService;

final class FinanceListService extends BaseService
{
    public function __construct(
        private readonly FinanceListQueryDao $queryDao,
    ) {
        parent::__construct();
    }

    public function execute(FinanceListFilterDto $filter): PagerDto
    {
        return $this->queryDao->execute($filter);
    }
}
