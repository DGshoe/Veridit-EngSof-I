<?php

namespace App\Auth;

use Illuminate\Auth\GenericUser;

/**
 * Authenticated user as asserted by the Identity JWT. Commerce stores no user
 * data: only the id (JWT "sub") and the profile (claim "type").
 *
 * @property-read string $id
 * @property-read string $type
 */
class IdentityUser extends GenericUser
{
    public function __construct(string $id, string $type, private readonly string $token)
    {
        parent::__construct(['id' => $id, 'type' => $type]);
    }

    public function isAdmin(): bool
    {
        return $this->type === 'admin';
    }

    /** Raw bearer token, forwarded to Identity when billing data is needed. */
    public function token(): string
    {
        return $this->token;
    }
}
