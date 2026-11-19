<?php

/*
|--------------------------------------------------------------------------
| Identity Service settings
|--------------------------------------------------------------------------
| Read through config() (never env() directly), so values keep working
| after "php artisan optimize" caches the configuration in production.
*/

return [

    // Front-end base URL, used in the password reset link (REQ02):
    // {FRONTEND_URL}/redefinir-senha?token=...&email=...
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    // Initial administrator, created by AdminSeeder. Never hard-code credentials.
    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrador'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
        'cpf' => env('ADMIN_CPF'),
    ],

];
