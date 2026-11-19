<?php

namespace App\Http\Middleware;

use App\Exceptions\Auth\AccountBlockedException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A blocked user is cut off immediately, even holding a still-valid token.
 * Runs after "auth:api" (see the "jwt" middleware group).
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isBlocked()) {
            throw new AccountBlockedException;
        }

        return $next($request);
    }
}
