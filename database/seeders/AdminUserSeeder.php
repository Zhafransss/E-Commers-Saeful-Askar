<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates (or updates) the initial store admin from ADMIN_* env variables.
 * Outside local/testing, credentials must be set explicitly.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            if (! app()->environment('local', 'testing')) {
                throw new RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD before seeding the admin account.');
            }

            $email = 'admin@saefulaskar.test';
            $password = 'password';
        }

        $admin = User::updateOrCreate(
            ['email' => mb_strtolower($email)],
            ['name' => env('ADMIN_NAME', 'Admin Saeful Askar'), 'password' => $password],
        );

        $admin->forceFill(['role' => UserRole::Admin, 'email_verified_at' => now()])->save();
    }
}
