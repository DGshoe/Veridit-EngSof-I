<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'package' => [
                'code' => $this->package_code,
                'credits' => $this->credits,
            ],
            'amount_cents' => $this->amount_cents,
            'amount' => Money::brl($this->amount_cents),
            'status' => $this->status->value,
            'payment' => [
                'method' => $this->payment_method->value,
                'provider_payment_id' => $this->provider_payment_id,
                'data' => $this->payment_data,
            ],
            'billing' => [
                'name' => $this->billing_name,
                'email' => $this->billing_email,
                'cpf' => $this->billing_cpf_masked,
            ],
            'paid_at' => $this->paid_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
