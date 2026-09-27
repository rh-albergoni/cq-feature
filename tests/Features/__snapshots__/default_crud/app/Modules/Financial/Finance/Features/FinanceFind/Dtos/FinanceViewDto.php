<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceFind\Dtos;

use App\Helpers\FromArrayTrait;
use App\Modules\Financial\Finance\Features\FinanceUpdate\Dtos\FinanceUpdateDto;

class FinanceViewDto extends FinanceUpdateDto
{
    use FromArrayTrait;

    public int $id;

    public int $created_by_user_id;

    public ?int $updated_by_user_id;

    public string $created_at;

    public ?string $updated_at;
}
