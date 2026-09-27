<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Shared\Repositories\Commands;

use App\Modules\Financial\Finance\Shared\Models\Finance;
use App\Shared\Cqs\Repositories\Commands\BaseCommandRepository;

class FinanceCommandRepository extends BaseCommandRepository
{
    public function __construct()
    {
        $this->model = new Finance;
    }
}
