<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceList\Dtos;

use App\Helpers\FromArrayTrait;
use App\Shared\Dtos\BaseDto;

class FinanceListDto extends BaseDto
{
    use FromArrayTrait;

    public int $id;

    public string $created_at;

    public ?string $updated_at;

    public int $created_by_user_id;

    public ?int $updated_by_user_id;

    public string $created_by_user_name;

    public ?string $updated_by_user_name;
}
