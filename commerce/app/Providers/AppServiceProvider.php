<?php

namespace App\Providers;

use App\Auth\IdentityTokenVerifier;
use App\Payments\Gateways\MercadoPagoGateway;
use App\Payments\Gateways\SimulatorGateway;
use Illuminate\Http\Client\Factory as HttpClient;
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

        $this->app->bind(SimulatorGateway::class, fn () => new SimulatorGateway(
            webhookSecret: config('commerce.payments.simulator.webhook_secret'),
        ));

        $this->app->bind(MercadoPagoGateway::class, fn ($app) => new MercadoPagoGateway(
            http: $app->make(HttpClient::class),
            config: (array) config('commerce.payments.mercadopago'),
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
