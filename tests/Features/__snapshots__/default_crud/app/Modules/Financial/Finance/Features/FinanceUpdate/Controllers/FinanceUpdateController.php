<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceUpdate\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Financial\Finance\Features\FinanceUpdate\Requests\FinanceUpdateRequest;
use App\Modules\Financial\Finance\Features\FinanceUpdate\Services\FinanceUpdateService;
use App\Shared\Utils\Response\Res;
use Illuminate\Http\JsonResponse;

class FinanceUpdateController extends Controller
{
    public function __construct(
        private readonly FinanceUpdateService $service
    ) {}

    public function execute(FinanceUpdateRequest $request, int $id): JsonResponse
    {
        $data = $request->validatedDto();
        $result = $this->service->execute($data, $id);

        return Res::success($result);
    }
}
