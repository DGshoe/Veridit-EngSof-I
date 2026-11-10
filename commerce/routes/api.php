<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PackageController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    // Public catalog (D2)
    Route::get('/packages', [PackageController::class, 'index']);
    Route::get('/credits/costs', [PackageController::class, 'captureCosts']);

    // Identity JWT (RS256, verified with Identity's public key)
    Route::middleware('auth:api')->group(function () {
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show'])->whereUuid('order');
    });
});
