<?php

namespace App\Exceptions\Auth;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Wrong e-mail or password. Generic on purpose: never reveal which one failed. */
class InvalidCredentialsException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'E-mail ou senha inválidos.'], 401);
    }
}
