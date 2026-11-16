<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Identity did not return the billing data (down, timeout, revoked token...). */
class IdentityUnavailableException extends RuntimeException
{
    public function __construct(private readonly string $reason = 'desconhecido')
    {
        parent::__construct('Identity indisponível: '.$reason);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Não foi possível obter seus dados de faturamento. Tente novamente em instantes.',
        ], 503);
    }

    /** Logs only the technical reason (status code / exception class), never personal data. */
    public function report(): void
    {
        Log::warning('Falha ao consultar o Identity (GET /me).', ['reason' => $this->reason]);
    }
}
