<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Service-to-service calls (/internal/*): header "X-Service-Token" must match
 * COMMERCE_SERVICE_TOKEN. Never exposed to the front-end.
 */
class EnsureServiceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('commerce.service_token');
        $given = (string) $request->header('X-Service-Token');

        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        return $next($request);
    }
}
