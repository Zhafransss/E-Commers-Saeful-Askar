<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * A customer only reaches their own orders (PRD §5 Keamanan). Callers turn a denial into
 * 404 ORDER_NOT_FOUND, so order numbers of others do not leak.
 */
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return (int) $order->user_id === (int) $user->id;
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }
}
