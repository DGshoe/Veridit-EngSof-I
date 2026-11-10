<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InternalCreditController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PackageController;
use App\Http\Controllers\Api\V1\SimulatorController;
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
        Route::post('/orders/{order}/simulate', [SimulatorController::class, 'simulate'])->whereUuid('order');

        Route::get('/wallet', [WalletController::class, 'show']);
        Route::get('/wallet/entries', [WalletController::class, 'entries']);
    });

    // Payment provider webhooks: authenticated by each provider's signature.
    Route::post('/webhooks/simulator', [SimulatorController::class, 'webhook']);

    // Service-to-service (Evidence). Never exposed to the front-end.
    Route::middleware('service')->prefix('internal')->group(function () {
        Route::post('/credits/consume', [InternalCreditController::class, 'consume']);
    });
});
