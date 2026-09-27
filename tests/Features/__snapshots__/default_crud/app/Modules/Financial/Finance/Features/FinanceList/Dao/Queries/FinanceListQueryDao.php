<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceList\Dao\Queries;

use App\Modules\Financial\Finance\Features\FinanceList\FilterDtos\FinanceListFilterDto;
use App\Modules\Financial\Finance\Shared\Dao\Queries\FinanceQueryDao;
use App\Shared\Dtos\OrderByDto;
use App\Shared\Dtos\PagerDto;
use App\Shared\FilterDtos\BaseListFilterDto;
use App\Shared\Repositories\FilterEloquentTrait;

class FinanceListQueryDao extends FinanceQueryDao
{
    use FilterEloquentTrait;

    /**
     * Listar registros
     *
     * @param  FinanceListFilterDto  $filter
     */
    public function execute(BaseListFilterDto|FinanceListFilterDto $filter): PagerDto
    {
        $this->ensureCallerIsAuthorized();

        $builder = $this->q()
            ->select([
                'finances.*',
                'created_by_users.name as created_by_user_name',
                'updated_by_users.name as updated_by_user_name',
            ])
            ->leftJoin('users as created_by_users', 'created_by_users.id', 'finances.created_by_user_id')
            ->leftJoin('users as updated_by_users', 'updated_by_users.id', 'finances.updated_by_user_id')
            ->when(empty($filter->order_by), function () use ($filter) {
                $filter->order_by = [OrderByDto::from(['column' => 'finances.id'])];
            });

        $pagerDto = $this->applyFilter($builder, $filter);
        $pagerDto->items = array_map(fn ($item) => $this->modelToListDto($item), $pagerDto->items);

        return $pagerDto;
    }

    /**
     * @return array<class-string>
     */
    protected function authorizedCallers(): array
    {
        return [
            'App\Modules\Financial\Finance\Features\FinanceList\Services\FinanceListService',
        ];
    }
}
