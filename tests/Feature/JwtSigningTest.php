<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\JwtKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR-001: tokens are signed with RS256. Only Identity holds the private key;
 * any service with the public key can verify a token but cannot forge one.
 */
class JwtSigningTest extends TestCase
{
    use RefreshDatabase;

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function b64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /** @return array{0: string, 1: string, 2: string} header, payload, signature (encoded) */
    private function parts(string $token): array
    {
        return explode('.', $token);
    }

    private function issuedToken(): string
    {
        return $this->loginAs(User::factory()->create());
    }

    public function test_token_is_signed_with_rs256_and_verifiable_with_the_public_key_only(): void
    {
        [$header, $payload, $signature] = $this->parts($this->issuedToken());

        $this->assertSame('RS256', json_decode($this->b64urlDecode($header), true)['alg']);

        $verified = openssl_verify("{$header}.{$payload}", $this->b64urlDecode($signature), self::jwtKeys()['public'], OPENSSL_ALGO_SHA256);
        $this->assertSame(1, $verified);
    }

    public function test_token_signed_with_another_private_key_is_rejected(): void
    {
        [, $payload] = $this->parts($this->issuedToken());
        $header = $this->b64url(json_encode(['typ' => 'JWT', 'alg' => 'RS256']));

        openssl_sign("{$header}.{$payload}", $signature, self::generateRsaPair()['private'], OPENSSL_ALGO_SHA256);
        $forged = "{$header}.{$payload}.".$this->b64url($signature);

        $this->getJson('/api/v1/me', $this->bearer($forged))->assertUnauthorized();
    }

    public function test_hs256_token_using_the_public_key_as_secret_is_rejected(): void
    {
        // Algorithm-confusion attack: whoever has the public key tries to sign with HMAC.
        [, $payload] = $this->parts($this->issuedToken());
        $header = $this->b64url(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $signature = hash_hmac('sha256', "{$header}.{$payload}", self::jwtKeys()['public'], true);

        $this->getJson('/api/v1/me', $this->bearer("{$header}.{$payload}.".$this->b64url($signature)))
            ->assertUnauthorized();
    }

    public function test_unsigned_token_is_rejected(): void
    {
        [, $payload] = $this->parts($this->issuedToken());
        $header = $this->b64url(json_encode(['typ' => 'JWT', 'alg' => 'none']));

        $this->getJson('/api/v1/me', $this->bearer("{$header}.{$payload}."))->assertUnauthorized();
    }

    public function test_jwt_keys_command_writes_the_pair_and_refuses_to_overwrite_without_force(): void
    {
        $envFile = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($envFile, "APP_NAME=Veridit\nJWT_ALGO=HS256\n");

        try {
            $this->artisan('jwt:keys', ['--env-file' => $envFile])->assertSuccessful();

            $contents = file_get_contents($envFile);
            $this->assertStringContainsString("JWT_ALGO=RS256\n", $contents);
            $this->assertMatchesRegularExpression('/^JWT_PRIVATE_KEY=base64:\S+$/m', $contents);
            $this->assertMatchesRegularExpression('/^JWT_PUBLIC_KEY=base64:\S+$/m', $contents);

            preg_match('/^JWT_PRIVATE_KEY=(\S+)$/m', $contents, $private);
            preg_match('/^JWT_PUBLIC_KEY=(\S+)$/m', $contents, $public);
            $privateKey = openssl_pkey_get_private(JwtKey::fromEnv($private[1]));
            $this->assertNotFalse($privateKey);
            $this->assertSame(trim(JwtKey::fromEnv($public[1])), trim(openssl_pkey_get_details($privateKey)['key']));

            $this->artisan('jwt:keys', ['--env-file' => $envFile])->assertFailed();
            $this->assertSame($contents, file_get_contents($envFile));
        } finally {
            @unlink($envFile);
        }
    }

    public function test_jwt_key_accepts_base64_and_escaped_pem(): void
    {
        $pem = self::jwtKeys()['public'];

        $this->assertSame($pem, JwtKey::fromEnv(JwtKey::encode($pem)));
        $this->assertSame($pem, JwtKey::fromEnv(str_replace("\n", '\n', $pem)));
        $this->assertNull(JwtKey::fromEnv(''));
        $this->assertNull(JwtKey::fromEnv(null));
    }
}
