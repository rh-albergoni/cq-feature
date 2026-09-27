<?php

declare(strict_types=1);

namespace App\Modules\Financial\Finance\Features\FinanceDelete\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Financial\Finance\Features\FinanceDelete\Services\FinanceDeleteService;
use App\Shared\Utils\Response\Res;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class FinanceDeleteController extends Controller
{
    public function __construct(
        private readonly FinanceDeleteService $service
    ) {}

    public function execute(int $id): JsonResponse
    {
        $this->service->execute($id);

        return Res::success(code: Response::HTTP_NO_CONTENT);
    }
}
