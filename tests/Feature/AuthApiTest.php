<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_logs_in_and_returns_user(): void
    {
        $this->postJson('/api/auth/register', [
            'email' => '  New.User@Example.com ',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'New User',
        ])
            ->assertCreated()
            ->assertJsonPath('email', 'new.user@example.com')
            ->assertJsonPath('role', 'user')
            ->assertJsonPath('auth_method', 'email')
            ->assertJsonMissingPath('password');

        $this->assertAuthenticated();
        // Same PHP process reuses the just-created model, so 201 can leak here; a real request returns 200.
        $this->getJson('/api/auth/me')->assertSuccessful()->assertJsonPath('email', 'new.user@example.com');
    }

    public function test_register_defaults_name_to_email_prefix(): void
    {
        $this->postJson('/api/auth/register', ['email' => 'jane@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertCreated()
            ->assertJsonPath('name', 'jane');
    }

    public function test_register_rejects_duplicate_and_short_password(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', ['email' => 'taken@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Email already registered');

        $this->postJson('/api/auth/register', ['email' => 'ok@example.com', 'password' => '123', 'password_confirmation' => '123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_register_with_admin_email_does_not_grant_admin(): void
    {
        // Admin row not seeded yet: registration must still produce a plain user.
        $this->postJson('/api/auth/register', ['email' => config('atglance.admin_email'), 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertCreated()
            ->assertJsonPath('role', 'user');
    }

    public function test_login_and_logout(): void
    {
        User::factory()->create(['email' => 'bob@example.com', 'password' => 'password123']);

        $this->postJson('/api/auth/login', ['email' => 'BOB@example.com', 'password' => 'password123'])
            ->assertOk()
            ->assertJsonPath('email', 'bob@example.com');
        $this->assertAuthenticated();

        $this->postJson('/api/auth/logout')->assertOk()->assertJson(['ok' => true]);
        $this->assertGuest();
    }

    public function test_login_rejects_bad_password(): void
    {
        User::factory()->create(['email' => 'bob@example.com', 'password' => 'password123']);

        $this->postJson('/api/auth/login', ['email' => 'bob@example.com', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Invalid email or password');
        $this->assertGuest();
    }

    public function test_me_requires_auth(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_admin_seeder_creates_and_resyncs_admin(): void
    {
        $this->seed(AdminUserSeeder::class);

        $admin = User::firstWhere('email', 'admin@atglance.test');
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue(Hash::check('secret-admin-pw', $admin->password));

        config(['atglance.admin_password' => 'rotated-pw']);
        $this->seed(AdminUserSeeder::class);

        $this->assertTrue(Hash::check('rotated-pw', $admin->fresh()->password));
        $this->assertSame(1, User::count());
    }
}
