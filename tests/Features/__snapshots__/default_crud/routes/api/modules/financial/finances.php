<?php

use App\Modules\Financial\Finance\Features\FinanceCreate\Controllers\FinanceCreateController;
use App\Modules\Financial\Finance\Features\FinanceDelete\Controllers\FinanceDeleteController;
use App\Modules\Financial\Finance\Features\FinanceUpdate\Controllers\FinanceUpdateController;
use App\Modules\Financial\Finance\Features\FinanceList\Controllers\FinanceListController;
use App\Modules\Financial\Finance\Features\FinanceFind\Controllers\FinanceFindController;
use Illuminate\Support\Facades\Route;

// financial/finances

Route::group([
    'middleware' => ['api', 'JWT', 'cors', 'localization'],
    'prefix' => 'financial',
], function () {
    Route::post('finances', [FinanceCreateController::class, 'execute']);
    Route::delete('finances/{id}', [FinanceDeleteController::class, 'execute']);
    Route::put('finances/{id}', [FinanceUpdateController::class, 'execute']);
    Route::get('finances', [FinanceListController::class, 'execute']);
    Route::get('finances/{id}', [FinanceFindController::class, 'execute']);
});
