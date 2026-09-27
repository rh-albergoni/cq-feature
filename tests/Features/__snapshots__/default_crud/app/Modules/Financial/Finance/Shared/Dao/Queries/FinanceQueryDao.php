<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Shared\Dao\Queries;

use App\Modules\Financial\Finance\Features\FinanceFind\Dtos\FinanceViewDto;
use App\Modules\Financial\Finance\Features\FinanceList\Dtos\FinanceListDto;
use App\Modules\Financial\Finance\Shared\Models\Finance;
use App\Shared\Cqs\Dao\Queries\BaseQueryDao;

class FinanceQueryDao extends BaseQueryDao
{
    public function __construct()
    {
        $this->model = new Finance;
    }

    /**
     * Mapear item para ViewDto
     */
    protected function modelToViewDto(array|\stdClass|Finance $item): FinanceViewDto
    {
        return FinanceViewDto::from($item);
    }

    /**
     * Mapear item para ListDto
     */
    protected function modelToListDto(array|\stdClass|Finance $item): FinanceListDto
    {
        return FinanceListDto::from($item);
    }
}
