<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Demo data never runs in production;
     * the real catalog comes from the Jubelio sync.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);

        if (app()->environment('local', 'testing')) {
            $this->call([
                CustomerUserSeeder::class,
                DemoCatalogSeeder::class,
                DemoArticleSeeder::class,
            ]);
        }
    }
}
