<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Password policy (§6): at least 8 characters, with letters and numbers.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // REQ03: 5 attempts per minute per IP + e-mail.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            'login|'.$request->ip().'|'.mb_strtolower(trim((string) $request->input('email')))
        ));

        // REQ02: 3 password-recovery requests per minute per IP.
        RateLimiter::for('forgot-password', fn (Request $request) => Limit::perMinute(3)->by(
            'forgot|'.$request->ip()
        ));
    }
}
