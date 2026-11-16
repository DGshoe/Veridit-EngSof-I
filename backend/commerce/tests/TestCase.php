<?php

namespace Tests;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /** @var array{private: string, public: string}|null RSA pair playing the role of Identity's keys */
    private static ?array $identityKeys = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Commerce only ever knows Identity's PUBLIC key.
        config(['commerce.identity.jwt_public_key' => self::identityKeys()['public']]);
    }

    /** Each request is authenticated only by its own headers. */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->forgetGuards();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /** @return array{private: string, public: string} */
    protected static function identityKeys(): array
    {
        return self::$identityKeys ??= self::generateRsaPair();
    }

    /** @return array{private: string, public: string} */
    protected static function generateRsaPair(): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        openssl_pkey_export($key, $private);

        return ['private' => $private, 'public' => openssl_pkey_get_details($key)['key']];
    }

    /** Token as Identity would issue it (RS256, claims sub/type/exp...). */
    protected function identityToken(?string $userId = null, string $type = 'comum', array $overrides = [], ?string $privateKey = null): string
    {
        $now = time();

        $claims = array_merge([
            'iss' => 'http://identity.test/api/v1/auth/login',
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 900,
            'jti' => Str::random(16),
            'sub' => $userId ?? (string) Str::uuid(),
            'type' => $type,
        ], $overrides);

        return JWT::encode($claims, $privateKey ?? self::identityKeys()['private'], 'RS256');
    }

    /** @return array<string, string> */
    protected function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    /** @return array<string, string> */
    protected function asUser(string $userId, string $type = 'comum'): array
    {
        return $this->bearer($this->identityToken($userId, $type));
    }
}
