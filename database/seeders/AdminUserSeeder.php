<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('atglance.admin_email');
        $password = config('atglance.admin_password');

        if (! $email || ! $password) {
            throw new RuntimeException('ADMIN_EMAIL and ADMIN_PASSWORD must be set to seed the admin user.');
        }

        $admin = User::firstOrNew(['email' => $email]);

        if (! $admin->exists) {
            $admin->name = 'AtGlance Admin';
            $admin->auth_method = 'email';
        }

        // Keep the admin password in sync with the environment.
        if (! $admin->password || ! Hash::check($password, $admin->password)) {
            $admin->password = $password;
        }

        $admin->role = User::ROLE_ADMIN;
        $admin->save();
    }
}
