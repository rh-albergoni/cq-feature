<?php

namespace Database\Factories\Modules\Financial\Finance\Shared\Models;

use App\Models\User;
use App\Modules\Financial\Finance\Shared\Models\Finance;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinanceFactory extends Factory
{
    protected $model = Finance::class;

    public function definition(): array
    {
        return [
            'created_by_user_id' => User::factory()->create()->id,
            'updated_by_user_id' => User::factory()->create()->id,
        ];
    }
}
