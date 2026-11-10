<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class InsufficientCreditsException extends RuntimeException
{
    public function __construct(public readonly int $required, public readonly int $balance)
    {
        parent::__construct('Saldo insuficiente.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Saldo insuficiente.',
            'errors' => ['balance' => ["Saldo insuficiente: são necessários {$this->required} crédito(s) e há {$this->balance}."]],
        ], 422);
    }

    public function report(): bool
    {
        return false; // expected business outcome
    }
}
