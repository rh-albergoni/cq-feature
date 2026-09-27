<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceCreate\Requests;

use App\Modules\Financial\Finance\Features\FinanceCreate\Dtos\FinanceCreateDto;
use Illuminate\Foundation\Http\FormRequest;

class FinanceCreateRequest extends FormRequest
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

    public function validatedDto(): FinanceCreateDto
    {
        $validated = parent::validated();

        return FinanceCreateDto::from($validated);
    }
}
