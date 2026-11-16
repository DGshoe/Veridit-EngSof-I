<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $provider
 * @property string $event_id
 * @property ?string $provider_payment_id
 * @property string $status
 * @property ?string $result
 * @property ?string $order_id
 * @property ?array $payload
 * @property ?Carbon $processed_at
 */
class PaymentEvent extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['provider', 'event_id', 'provider_payment_id', 'status', 'result', 'order_id', 'payload', 'processed_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
