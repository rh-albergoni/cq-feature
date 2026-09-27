<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceList\FilterDtos;

use App\Helpers\FromArrayTrait;
use App\Shared\FilterDtos\BaseListFilterDto;

final class FinanceListFilterDto extends BaseListFilterDto
{
    use FromArrayTrait;
}
