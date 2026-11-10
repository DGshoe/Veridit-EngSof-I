<?php

namespace App\Providers;

use App\Auth\IdentityTokenVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IdentityTokenVerifier::class, fn () => new IdentityTokenVerifier(
            publicKey: config('commerce.identity.jwt_public_key'),
            leeway: (int) config('commerce.identity.jwt_leeway', 0),
        ));
    }

    public function boot(): void
    {
        // Guard "api": the Identity JWT (RS256) verified with Identity's public key.
        Auth::viaRequest('identity-jwt', fn (Request $request) => $this->app
            ->make(IdentityTokenVerifier::class)
            ->userFromRequest($request));
    }
}
