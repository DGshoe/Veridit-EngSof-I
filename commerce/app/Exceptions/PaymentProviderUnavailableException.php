<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** The provider could not open the charge. The order is marked as failed. */
class PaymentProviderUnavailableException extends RuntimeException
{
    public function __construct(private readonly string $provider, private readonly string $reason)
    {
        parent::__construct("Provedor {$provider} indisponível: {$reason}");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Não foi possível iniciar o pagamento agora. Nenhum valor foi cobrado; tente novamente.',
        ], 503);
    }

    public function report(): void
    {
        Log::warning('Falha ao abrir cobrança no provedor.', ['provider' => $this->provider, 'reason' => $this->reason]);
    }
}
