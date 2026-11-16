<?php

namespace App\Policies;

use App\Auth\IdentityUser;
use App\Models\Order;

class OrderPolicy
{
    /** Owner or admin. */
    public function view(IdentityUser $user, Order $order): bool
    {
        return $user->isAdmin() || $order->belongsToUser($user->id);
    }

    /** Only the owner pays (simulates) their own order. */
    public function pay(IdentityUser $user, Order $order): bool
    {
        return $order->belongsToUser($user->id);
    }
}
