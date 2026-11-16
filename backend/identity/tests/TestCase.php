<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @var array{private: string, public: string}|null RSA pair shared by the whole test run */
    private static ?array $jwtKeys = null;

    protected function setUp(): void
    {
        parent::setUp();

        // RS256 keys generated in memory once per run: no key is ever committed.
        config(['jwt.algo' => 'RS256', 'jwt.keys.private' => self::jwtKeys()['private'], 'jwt.keys.public' => self::jwtKeys()['public']]);
    }

    /** @return array{private: string, public: string} */
    protected static function jwtKeys(): array
    {
        return self::$jwtKeys ??= self::generateRsaPair();
    }

    /** @return array{private: string, public: string} */
    protected static function generateRsaPair(): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        openssl_pkey_export($key, $private);

        return ['private' => $private, 'public' => openssl_pkey_get_details($key)['key']];
    }

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
