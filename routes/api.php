<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Identity Service API — v1
|--------------------------------------------------------------------------
| Every route here is served under /api/v1 and always answers in JSON.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/health', HealthController::class)->name('health');
});
