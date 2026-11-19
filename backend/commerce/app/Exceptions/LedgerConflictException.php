<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** The same reference was already booked with a different amount/reason. */
class LedgerConflictException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Esta referência já foi lançada com outro valor ou motivo.',
        ], 409);
    }

    public function report(): bool
    {
        return false;
    }
}
