<?php

use App\Support\PemKey;

return [

    /*
    | D2 — packages (credits decided by the team; prices from the team's design,
    | overridable per environment). Prices in cents (BRL).
    */
    'packages' => [
        'basico' => ['name' => 'Básico', 'credits' => 10, 'price_cents' => (int) env('PACKAGE_BASICO_PRICE_CENTS', 3000)],
        'medio' => ['name' => 'Médio', 'credits' => 25, 'price_cents' => (int) env('PACKAGE_MEDIO_PRICE_CENTS', 6500)],
        'premium' => ['name' => 'Premium', 'credits' => 50, 'price_cents' => (int) env('PACKAGE_PREMIUM_PRICE_CENTS', 12000)],
    ],

    // Credits consumed by each capture type (team decision). Credits never expire.
    'capture_costs' => [
        'imagem' => 1,
        'video' => 2,
    ],

    'identity' => [
        // Internal URL of the Identity API (billing data: GET /api/v1/me).
        'url' => rtrim((string) env('IDENTITY_URL', 'http://web'), '/'),
        'timeout' => (int) env('IDENTITY_TIMEOUT', 5),

        // ADR-001: Identity's PUBLIC key only. Commerce can verify tokens, never issue them.
        'jwt_public_key' => PemKey::fromEnv(env('IDENTITY_JWT_PUBLIC_KEY')),
        'jwt_leeway' => (int) env('IDENTITY_JWT_LEEWAY', 0),
    ],

    // Token used by other services (Evidence) to call /internal/* endpoints.
    'service_token' => env('COMMERCE_SERVICE_TOKEN'),

    'payments' => [
        'simulator' => [
            // T06 "Simular confirmação / Simular falha". Never enable in real production.
            'enabled' => (bool) env('PAYMENTS_SIMULATOR_ENABLED', false),
            'webhook_secret' => env('PAYMENTS_SIMULATOR_WEBHOOK_SECRET'),
        ],
    ],

];
