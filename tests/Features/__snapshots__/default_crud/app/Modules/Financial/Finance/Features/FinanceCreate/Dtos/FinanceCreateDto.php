<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceCreate\Dtos;

use App\Helpers\FromArrayTrait;
use App\Shared\Dtos\BaseDto;

class FinanceCreateDto extends BaseDto
{
    use FromArrayTrait;
}
