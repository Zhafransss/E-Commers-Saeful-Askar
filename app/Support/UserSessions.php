<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signs a user out on other devices after a password change (AUTH-07) or
 * everywhere after a reset (AUTH-09).
 *
 * Sessions can only be listed per user with the `database` session driver,
 * which every environment uses (SESSION_DRIVER=database). The remember-me
 * token is rotated as well, so a "remember me" cookie on another device
 * cannot log it back in.
 */
class UserSessions
{
    public static function endAll(User $user, ?string $exceptSessionId = null): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->when($exceptSessionId !== null, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->delete();
    }
}
