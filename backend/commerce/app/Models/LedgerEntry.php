<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Immutable ledger entry: created once, never updated or deleted.
 *
 * @property string $id
 * @property string $user_id
 * @property LedgerEntryType $type
 * @property int $amount
 * @property int $balance_after
 * @property string $reason
 * @property string $reference_type
 * @property string $reference_id
 * @property Carbon $created_at
 */
class LedgerEntry extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'type', 'amount', 'balance_after', 'reason', 'reference_type', 'reference_id'];

    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Lançamentos do extrato são imutáveis.'));
        static::deleting(fn () => throw new LogicException('Lançamentos do extrato são imutáveis.'));
    }
}
