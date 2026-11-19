<?php

namespace App\Support;

use App\Models\User;

/** An access token just issued for a user (login or refresh). */
final readonly class IssuedToken
{
    public function __construct(
        public string $accessToken,
        public User $user,
        public int $expiresIn,
    ) {}
}
