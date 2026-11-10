<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsumeCreditsRequest;
use App\Http\Resources\LedgerEntryResource;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;

/**
 * Contract for Evidence (ADR-011, Figure 4): "credit consumption uses a
 * Commerce contract, without access to its database".
 */
class InternalCreditController extends Controller
{
    public function __construct(private readonly WalletService $wallet) {}

    /** 201 debited now · 200 already debited for this capture (idempotent) · 422 insufficient balance. */
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
