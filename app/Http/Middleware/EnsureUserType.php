<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RBAC by profile: ->middleware('type:admin') or ->middleware('type:advogado,comum').
 * Must run after the "auth:api" middleware.
 */
class EnsureUserType
{
    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $user = $request->user();

        if (! $user) {
            throw new AuthenticationException;
        }

        $allowed = array_map(fn (string $t) => UserType::from($t), $types);

        if (! in_array($user->type, $allowed, true)) {
            abort(403);
        }

        return $next($request);
    }
}
