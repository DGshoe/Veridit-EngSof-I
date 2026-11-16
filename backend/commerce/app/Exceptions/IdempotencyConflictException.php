<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** The same Idempotency-Key was reused with a different request body. */
class IdempotencyConflictException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Esta Idempotency-Key já foi usada em outro pedido com dados diferentes.',
        ], 409);
    }

    public function report(): bool
    {
        return false;
    }
}
