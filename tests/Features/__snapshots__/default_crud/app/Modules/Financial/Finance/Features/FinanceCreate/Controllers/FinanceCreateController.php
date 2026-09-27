<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceCreate\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Financial\Finance\Features\FinanceCreate\Requests\FinanceCreateRequest;
use App\Modules\Financial\Finance\Features\FinanceCreate\Services\FinanceCreateService;
use App\Shared\Utils\Response\Res;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class FinanceCreateController extends Controller
{
    public function __construct(
        private readonly FinanceCreateService $service
    ) {}

    public function execute(FinanceCreateRequest $request): JsonResponse
    {
        $data = $request->validatedDto();
        $result = $this->service->execute($data);

        return Res::success($result, Response::HTTP_CREATED);
    }
}
