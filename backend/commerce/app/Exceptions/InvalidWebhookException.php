<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Webhook with missing/invalid signature or malformed body. No details in the response. */
class InvalidWebhookException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Notificação inválida.'], 401);
    }

    public function report(): bool
    {
        return false;
    }
}
