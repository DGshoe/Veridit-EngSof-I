<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $package_id
 * @property string $package_code
 * @property int $credits
 * @property int $amount_cents
 * @property OrderStatus $status
 * @property PaymentMethod $payment_method
 * @property ?string $provider_payment_id
 * @property ?array $payment_data
 * @property ?string $idempotency_key
 * @property string $billing_name
 * @property string $billing_email
 * @property ?string $billing_cpf_masked
 * @property ?Carbon $paid_at
 * @property ?Carbon $failed_at
 * @property Carbon $created_at
 */
class Order extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'package_id', 'package_code', 'credits', 'amount_cents', 'status',
        'payment_method', 'provider_payment_id', 'payment_data', 'idempotency_key',
        'billing_name', 'billing_email', 'billing_cpf_masked', 'paid_at', 'failed_at',
    ];

    protected $attributes = [
        'status' => 'pendente',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_data' => 'array',
            'credits' => 'integer',
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function belongsToUser(string $userId): bool
    {
        return $this->user_id === $userId;
    }
}
