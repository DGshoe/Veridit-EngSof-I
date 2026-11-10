<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Each HTTP call in a test reuses the same application instance. Reset the
     * auth guards and the JWT token cached by jwt-auth so every request is
     * authenticated only by its own headers, as in production.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /** Logs in through the API and returns the access token. */
    protected function loginAs(User $user, string $password = 'Senha1234'): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ])->assertOk()->json('data.access_token');
    }

    /** @return array<string, string> */
    protected function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }
}
