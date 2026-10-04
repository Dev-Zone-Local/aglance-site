<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_name(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Old']))
            ->putJson('/api/account/profile', ['name' => '  New Name '])
            ->assertOk()
            ->assertJsonPath('name', 'New Name');

        $this->putJson('/api/account/profile', ['name' => ''])->assertUnprocessable();
    }

    public function test_change_password_needs_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-pass']);
        $this->actingAs($user);

        $this->putJson('/api/account/password', ['current_password' => 'wrong', 'password' => 'new-pass', 'password_confirmation' => 'new-pass'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.current_password.0', 'The current password is incorrect.');

        $this->putJson('/api/account/password', ['current_password' => 'old-pass', 'password' => 'new-pass', 'password_confirmation' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'Passwords do not match');

        $this->putJson('/api/account/password', ['current_password' => 'old-pass', 'password' => 'new-pass', 'password_confirmation' => 'new-pass'])
            ->assertOk();
        $this->assertTrue(Hash::check('new-pass', $user->fresh()->password));
    }

    public function test_github_user_sets_first_password_without_current(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)
            ->putJson('/api/account/password', ['password' => 'first-pass', 'password_confirmation' => 'first-pass'])
            ->assertOk();
        $this->assertTrue(Hash::check('first-pass', $user->fresh()->password));
    }

    public function test_guests_rejected(): void
    {
        $this->putJson('/api/account/profile', ['name' => 'x'])->assertUnauthorized();
        $this->putJson('/api/account/password', ['password' => 'x'])->assertUnauthorized();
    }
}
