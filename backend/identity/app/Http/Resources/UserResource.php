<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\Mask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public representation of a user. Never exposes the password, and the CPF
 * is always masked (ADR-012).
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'cpf' => Mask::cpf($this->cpf),
            'type' => $this->type->value,
            'oab' => $this->oab(),
            'status' => $this->status->value,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
