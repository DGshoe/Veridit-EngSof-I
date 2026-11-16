<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /** GET /users — admin only (the route also uses "type:admin"). */
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    /** GET /users/{id} — admin, or the user themself. */
    public function view(User $actor, User $target): bool
    {
        return $actor->isAdmin() || $actor->is($target);
    }
}
