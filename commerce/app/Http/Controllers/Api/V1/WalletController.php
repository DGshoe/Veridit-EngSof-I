<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListEntriesRequest;
use App\Http\Resources\LedgerEntryResource;
use App\Http\Resources\PaginatedCollection;
use App\Models\LedgerEntry;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $wallet) {}

    /** Current balance of the logged user. */
    #[OA\Get(
        path: '/api/v1/wallet',
        operationId: 'wallet',
        summary: 'Saldo de créditos do usuário logado',
        security: [['bearerAuth' => []]],
        tags: ['Carteira'],
        responses: [
            new OA\Response(response: 200, description: 'Saldo', content: new OA\JsonContent(
                example: ['data' => ['user_id' => '01a1c556-b752-722d-a4e1-41f909b8bad6', 'balance' => 24]],
            )),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        ],
    )]
    public function show(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        return response()->json(['data' => [
            'user_id' => $userId,
            'balance' => $this->wallet->balanceOf($userId),
        ]]);
    }

    /** Statement (ledger entries) of the logged user, newest first. */
    #[OA\Get(
        path: '/api/v1/wallet/entries',
        operationId: 'walletEntries',
        summary: 'Extrato de créditos do usuário logado',
        description: 'Lançamentos imutáveis (compras e capturas), mais recentes primeiro.',
        security: [['bearerAuth' => []]],
        tags: ['Carteira'],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['credito', 'debito'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15, maximum: 50, minimum: 1)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Extrato paginado', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/LedgerEntry')),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ])),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
        ],
    )]
    public function entries(ListEntriesRequest $request): PaginatedCollection
    {
        $entries = LedgerEntry::query()
            ->where('user_id', $request->user()->id)
            ->when($request->validated('type'), fn ($q, $type) => $q->where('type', $type))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return new PaginatedCollection($entries, LedgerEntryResource::class);
    }
}
