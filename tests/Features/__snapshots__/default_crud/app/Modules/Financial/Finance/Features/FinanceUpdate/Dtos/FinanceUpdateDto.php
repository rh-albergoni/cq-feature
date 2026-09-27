<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceUpdate\Dtos;

use App\Helpers\FromArrayTrait;
use App\Modules\Financial\Finance\Features\FinanceCreate\Dtos\FinanceCreateDto;

class FinanceUpdateDto extends FinanceCreateDto
{
    use FromArrayTrait;
}
