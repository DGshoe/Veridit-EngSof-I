<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PasswordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Identity Service API — v1
|--------------------------------------------------------------------------
| Every route here is served under /api/v1 and always answers in JSON.
| "jwt" = auth:api + rejection of tokens issued before a password change.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/health', HealthController::class)->name('health');

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->name('register');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
        // Not behind "jwt" on purpose: an expired token may still be refreshed
        // inside the refresh window; the service validates it.
        Route::post('/refresh', [AuthController::class, 'refresh'])->name('refresh');

        Route::post('/forgot-password', [PasswordController::class, 'forgot'])
            ->middleware('throttle:forgot-password')->name('forgot-password');
        Route::post('/reset-password', [PasswordController::class, 'reset'])->name('reset-password');

        Route::middleware('jwt')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        });
    });
});
