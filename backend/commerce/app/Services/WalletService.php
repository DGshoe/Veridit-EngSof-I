<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\LedgerConflictException;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Ledger/Wallet module (ADR-002/011).
 *
 * - Balance and ledger entry are written in the same transaction.
 * - The wallet row is locked (SELECT ... FOR UPDATE), so concurrent operations
 *   on the same user are serialized.
 * - Idempotent by reference: booking the same (reference_type, reference_id,
 *   type) twice returns the first entry and does not change the balance.
 */
class WalletService
{
    public function balanceOf(string $userId): int
    {
        return (int) (Wallet::query()->whereKey($userId)->value('balance') ?? 0);
    }

    /** @return array{0: LedgerEntry, 1: bool} [entry, created now?] */
    public function credit(string $userId, int $amount, string $reason, string $referenceType, string $referenceId): array
    {
        return $this->book(LedgerEntryType::Credit, $userId, $amount, $reason, $referenceType, $referenceId);
    }

    /**
     * @return array{0: LedgerEntry, 1: bool} [entry, created now?]
     *
     * @throws InsufficientCreditsException
     */
    public function debit(string $userId, int $amount, string $reason, string $referenceType, string $referenceId): array
    {
        return $this->book(LedgerEntryType::Debit, $userId, $amount, $reason, $referenceType, $referenceId);
    }

    /** @return array{0: LedgerEntry, 1: bool} */
    private function book(LedgerEntryType $type, string $userId, int $amount, string $reason, string $referenceType, string $referenceId): array
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('O valor do lançamento deve ser positivo.');
        }

        return DB::transaction(function () use ($type, $userId, $amount, $reason, $referenceType, $referenceId) {
            Wallet::query()->insertOrIgnore([
                'user_id' => $userId, 'balance' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);

            /** @var Wallet $wallet */
            $wallet = Wallet::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

            $existing = LedgerEntry::query()
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->where('type', $type)
                ->first();

            if ($existing) {
                if ($existing->user_id !== $userId || $existing->amount !== $amount || $existing->reason !== $reason) {
                    throw new LedgerConflictException;
                }

                return [$existing, false];
            }

            $newBalance = $type === LedgerEntryType::Credit ? $wallet->balance + $amount : $wallet->balance - $amount;

            if ($newBalance < 0) {
                throw new InsufficientCreditsException($amount, $wallet->balance);
            }

            $wallet->update(['balance' => $newBalance]);

            $entry = LedgerEntry::query()->create([
                'user_id' => $userId,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            return [$entry, true];
        });
    }
}
