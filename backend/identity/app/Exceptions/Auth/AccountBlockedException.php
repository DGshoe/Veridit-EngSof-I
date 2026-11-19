<?php

namespace App\Exceptions\Auth;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class AccountBlockedException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Usuário bloqueado. Entre em contato com o suporte.'], 403);
    }
}
