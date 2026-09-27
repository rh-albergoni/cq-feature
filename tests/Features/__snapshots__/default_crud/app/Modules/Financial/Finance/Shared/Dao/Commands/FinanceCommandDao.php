<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Shared\Dao\Commands;

use App\Modules\Financial\Finance\Shared\Models\Finance;
use App\Shared\Cqs\Dao\Commands\BaseCommandDao;

class FinanceCommandDao extends BaseCommandDao
{
    public function __construct()
    {
        $this->model = new Finance;
    }
}
