<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample customers for trying the storefront (login, cart, address book, checkout).
 * Every account uses the password "password", so this only ever runs locally.
 */
class CustomerUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $customers = [
            ['Zhafran', 'zhafran@gmail.com', '081200000001'],
            ['Ataka', 'ataka@gmail.com', '081200000002'],
            ['Konsumen', 'konsumen@gmail.com', '081200000003'],
            ['Budi Santoso', 'budi@example.com', '081200000004'],
            ['Siti Rahmawati', 'siti@example.com', '081200000005'],
        ];

        foreach ($customers as [$name, $email, $phone]) {
            $customer = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'phone' => $phone, 'password' => 'password'],
            );

            $customer->forceFill(['role' => UserRole::Customer, 'email_verified_at' => now()])->save();
        }
    }
}
