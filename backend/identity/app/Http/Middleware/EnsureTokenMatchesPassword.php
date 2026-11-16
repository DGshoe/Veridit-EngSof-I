<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use Symfony\Component\HttpFoundation\Response;

/**
 * REQ02: tokens issued before the last password change are rejected.
 * Runs right after "auth:api" (see the "jwt" middleware group).
 */
class EnsureTokenMatchesPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');
        $user = $guard->user();

        if (! $user instanceof User || ! hash_equals($user->passwordVersion(), (string) $guard->getPayload()->get('pwv'))) {
            throw new AuthenticationException;
        }

        return $next($request);
    }
}
