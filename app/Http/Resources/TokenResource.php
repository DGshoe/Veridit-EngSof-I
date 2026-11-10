<?php

namespace App\Http\Resources;

use App\Support\IssuedToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property IssuedToken $resource
 */
class TokenResource extends JsonResource
{
    public function __construct(IssuedToken $token)
    {
        parent::__construct($token);
    }

    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->resource->accessToken,
            'token_type' => 'bearer',
            'expires_in' => $this->resource->expiresIn,
            'user' => new UserResource($this->resource->user),
        ];
    }
}
