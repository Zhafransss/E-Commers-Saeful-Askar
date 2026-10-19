<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;

/**
 * A customer only reaches their own addresses (PRD §5 Keamanan). Callers turn
 * a denial into 404 ADDRESS_NOT_FOUND, so address ids of others do not leak.
 */
class AddressPolicy
{
    public function view(User $user, Address $address): bool
    {
        return (int) $address->user_id === (int) $user->id;
    }

    public function update(User $user, Address $address): bool
    {
        return (int) $address->user_id === (int) $user->id;
    }

    public function delete(User $user, Address $address): bool
    {
        return (int) $address->user_id === (int) $user->id;
    }
}
