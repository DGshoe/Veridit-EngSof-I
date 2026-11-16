<?php

namespace App\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Verifies the access token issued by Identity (ADR-001).
 *
 * The algorithm is pinned to RS256: a token whose header says HS256 or "none"
 * is rejected, so holding the public key is not enough to forge a token.
 *
 * Limitation (documented): Identity's blacklist (logout) and password-version
 * claim are not visible here, so a revoked token stays valid until it expires
 * (15 minutes at most).
 */
class IdentityTokenVerifier
{
    public function __construct(
        private readonly ?string $publicKey,
        private readonly int $leeway = 0,
    ) {}

    public function userFromRequest(Request $request): ?IdentityUser
    {
        $token = $request->bearerToken();

        return $token ? $this->verify($token) : null;
    }

    public function verify(string $token): ?IdentityUser
    {
        if (! $this->publicKey) {
            return null; // misconfigured: fail closed
        }

        try {
            JWT::$leeway = $this->leeway;
            $claims = JWT::decode($token, new Key($this->publicKey, 'RS256'));
        } catch (Throwable) {
            return null;
        }

        $id = $claims->sub ?? null;
        $type = $claims->type ?? null;

        if (! is_string($id) || ! Str::isUuid($id) || ! is_string($type) || $type === '') {
            return null;
        }

        return new IdentityUser($id, $type, $token);
    }
}
