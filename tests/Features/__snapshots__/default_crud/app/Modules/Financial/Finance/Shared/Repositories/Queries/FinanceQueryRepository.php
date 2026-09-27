<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Shared\Repositories\Queries;

use App\Modules\Financial\Finance\Shared\Entities\FinanceEntity;
use App\Modules\Financial\Finance\Shared\Models\Finance;
use App\Shared\Cqs\Repositories\Queries\BaseQueryRepository;
use Illuminate\Database\Eloquent\Builder;
use stdClass;

class FinanceQueryRepository extends BaseQueryRepository
{
    public function __construct()
    {
        $this->model = new Finance;
    }

    /**
     * Mapear item para Entity
     */
    protected function modelToEntity(array|stdClass|Finance $item): FinanceEntity
    {
        return FinanceEntity::from($item);
    }

    /**
     * Consulta base para retornar dados de Entity
     */
    protected function baseQuery(): Builder
    {
        return $this->model
            ->select(['finances.*']);
    }
}
