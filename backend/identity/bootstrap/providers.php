<?php

use App\Providers\AppServiceProvider;

return array_values(array_filter([
    AppServiceProvider::class,

    // Optional machine-local extensions (app/Local/, git-ignored): loaded only when present.
    class_exists('App\Local\LocalServiceProvider') ? 'App\Local\LocalServiceProvider' : null,
]));
