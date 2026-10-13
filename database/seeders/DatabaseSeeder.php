<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // No admin here: create it with `php artisan atglance:make-admin`.
        $this->call([
            ContentSeeder::class,
            TestUserSeeder::class,
        ]);
    }
}
