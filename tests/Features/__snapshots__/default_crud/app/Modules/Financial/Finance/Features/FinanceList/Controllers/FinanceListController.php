<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceList\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Financial\Finance\Features\FinanceList\Requests\FinanceListRequest;
use App\Modules\Financial\Finance\Features\FinanceList\Services\FinanceListService;
use App\Shared\Utils\Response\Res;
use Illuminate\Http\JsonResponse;

final class FinanceListController extends Controller
{
    public function __construct(
        private readonly FinanceListService $service
    ) {}

    public function execute(FinanceListRequest $request): JsonResponse
    {
        $filter = $request->validatedDto();
        $result = $this->service->execute($filter);

        return Res::success($result);
    }
}
