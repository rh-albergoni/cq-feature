<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceUpdate\Requests;

use App\Modules\Financial\Finance\Features\FinanceCreate\Requests\FinanceCreateRequest;
use App\Modules\Financial\Finance\Features\FinanceUpdate\Dtos\FinanceUpdateDto;

class FinanceUpdateRequest extends FinanceCreateRequest
{
    public function validatedDto(): FinanceUpdateDto
    {
        $validated = parent::validated();

        return FinanceUpdateDto::from($validated);
    }
}
