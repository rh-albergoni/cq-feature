<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceFind\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Financial\Finance\Features\FinanceFind\Services\FinanceFindService;
use App\Shared\Utils\Response\Res;
use Illuminate\Http\JsonResponse;

final class FinanceFindController extends Controller
{
    public function __construct(
        private readonly FinanceFindService $service
    ) {}

    public function execute(int $id): JsonResponse
    {
        $result = $this->service->execute($id);

        return Res::success($result);
    }
}
