<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A verified regular user for trying the dashboard locally. Runs only in the local
 * environment and only when TEST_USER_EMAIL and TEST_USER_PASSWORD are set in .env.
 */
class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('TEST_USER_EMAIL');
        $password = env('TEST_USER_PASSWORD');

        if (! app()->isLocal() || blank($email) || blank($password)) {
            return;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => 'Local Test User', 'password' => $password, 'role' => 'user', 'auth_method' => 'email'],
        );
        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
    }
}
