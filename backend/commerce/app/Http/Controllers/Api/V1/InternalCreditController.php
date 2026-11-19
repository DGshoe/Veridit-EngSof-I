<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsumeCreditsRequest;
use App\Http\Resources\LedgerEntryResource;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Contract for Evidence (ADR-011, Figure 4): "credit consumption uses a
 * Commerce contract, without access to its database".
 */
class InternalCreditController extends Controller
{
    public function __construct(private readonly WalletService $wallet) {}

    /** 201 debited now · 200 already debited for this capture (idempotent) · 422 insufficient balance. */
    #[OA\Post(
        path: '/api/v1/internal/credits/consume',
        operationId: 'consumeCredits',
        summary: 'Debitar créditos de uma captura (contrato com o Evidence)',
        description: 'Uso exclusivo de serviços do backend. Imagem custa 1 crédito e vídeo 2. '
            .'Idempotente por `capture_id`: repetir a chamada não debita de novo (200).',
        security: [['serviceToken' => []]],
        tags: ['Interno'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['user_id', 'capture_id', 'capture_type'],
            properties: [
                new OA\Property(property: 'user_id', type: 'string', format: 'uuid', example: '01a1c556-b752-722d-a4e1-41f909b8bad6'),
                new OA\Property(property: 'capture_id', type: 'string', maxLength: 100, example: 'cap_9a8b7c6d'),
                new OA\Property(property: 'capture_type', type: 'string', enum: ['imagem', 'video'], example: 'video'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Créditos debitados', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'entry', ref: '#/components/schemas/LedgerEntry'),
                    new OA\Property(property: 'balance', type: 'integer', example: 8),
                ], type: 'object'),
                new OA\Property(property: 'message', type: 'string', example: 'Créditos debitados.'),
            ])),
            new OA\Response(response: 200, description: 'Captura já debitada anteriormente (nenhum novo débito)', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'object'),
                new OA\Property(property: 'message', type: 'string', example: 'Captura já debitada anteriormente.'),
            ])),
            new OA\Response(response: 401, description: 'Token de serviço ausente ou inválido', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'Mesmo capture_id já debitado com outro tipo', content: new OA\JsonContent(ref: '#/components/schemas/Error',
                example: ['message' => 'Esta referência já foi lançada com outro valor ou motivo.'])),
            new OA\Response(response: 422, description: 'Dados inválidos ou saldo insuficiente', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError',
                example: ['message' => 'Saldo insuficiente.', 'errors' => ['balance' => ['Saldo insuficiente: são necessários 2 crédito(s) e há 1.']]])),
        ],
    )]
    public function consume(ConsumeCreditsRequest $request): JsonResponse
    {
        $type = $request->captureType();

        [$entry, $created] = $this->wallet->debit(
            userId: $request->validated('user_id'),
            amount: $type->cost(),
            reason: $type->reason(),
            referenceType: 'capture',
            referenceId: $request->validated('capture_id'),
        );

        return response()->json([
            'data' => [
                'entry' => new LedgerEntryResource($entry),
                'balance' => $this->wallet->balanceOf($entry->user_id),
            ],
            'message' => $created ? 'Créditos debitados.' : 'Captura já debitada anteriormente.',
        ], $created ? 201 : 200);
    }
}
