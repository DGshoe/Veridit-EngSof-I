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

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $wallet) {}

    /** Current balance of the logged user. */
    public function show(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        return response()->json(['data' => [
            'user_id' => $userId,
            'balance' => $this->wallet->balanceOf($userId),
        ]]);
    }

    /** Statement (ledger entries) of the logged user, newest first. */
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
