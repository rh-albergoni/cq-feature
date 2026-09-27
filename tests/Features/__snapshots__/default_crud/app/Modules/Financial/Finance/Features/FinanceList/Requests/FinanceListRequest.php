<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceList\Requests;

use App\Modules\Financial\Finance\Features\FinanceList\FilterDtos\FinanceListFilterDto;
use Illuminate\Foundation\Http\FormRequest;

class FinanceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            '*' => 'nullable',
        ];
    }

    public function validatedDto(): FinanceListFilterDto
    {
        $validated = parent::validated();

        return FinanceListFilterDto::from($validated);
    }
}
