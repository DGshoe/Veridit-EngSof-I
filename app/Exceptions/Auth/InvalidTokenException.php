<?php

namespace App\Exceptions\Auth;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Token missing, malformed, blacklisted or outside the refresh window. */
class InvalidTokenException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Token inválido ou expirado.'], 401);
    }
}
