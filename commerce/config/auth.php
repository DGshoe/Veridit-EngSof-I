<?php

return [

    /*
    | Commerce has no users table: users live in Identity (ADR-012). Requests are
    | authenticated by the Identity JWT (RS256), verified locally with Identity's
    | public key (see App\Auth\IdentityTokenGuard, registered in AppServiceProvider).
    */

    'defaults' => [
        'guard' => 'api',
    ],

    'guards' => [
        'api' => [
            'driver' => 'identity-jwt',
        ],
    ],

    'providers' => [],

    'passwords' => [],

];
