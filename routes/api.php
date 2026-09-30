<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MemoController;
use App\Http\Controllers\Api\V1\StableController;
use Illuminate\Support\Facades\Route;

/*
| Companion API. The stable tenant code is the only gate.
| User authentication for the native app is intentionally out of scope.
*/

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);
    Route::get('/stables/{code}', [StableController::class, 'show'])->middleware('throttle:stables');
    Route::post('/memos', [MemoController::class, 'store'])->middleware('throttle:memos');
});
