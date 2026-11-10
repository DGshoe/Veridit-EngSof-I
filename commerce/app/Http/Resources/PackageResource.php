<?php

namespace App\Http\Resources;

use App\Models\Package;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Package */
class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'credits' => $this->credits,
            'price_cents' => $this->price_cents,
            'price' => Money::brl($this->price_cents),
            'price_per_credit' => Money::brl((int) round($this->price_cents / $this->credits)),
        ];
    }
}
