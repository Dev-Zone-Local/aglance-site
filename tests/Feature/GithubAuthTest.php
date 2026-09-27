<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GithubAuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGithubUser(array $attrs): void
    {
        $gh = (new SocialiteUser)->map(array_merge([
            'id' => 4242,
            'nickname' => 'octocat',
            'name' => 'Octo Cat',
            'email' => null,
            'avatar' => 'https://avatars.example/octocat.png',
        ], $attrs));

        Socialite::shouldReceive('driver->user')->andReturn($gh);
    }

    private function postCallback()
    {
        return $this->postJson('/api/auth/github/callback?code=abc&state=xyz');
    }

    public function test_start_returns_github_authorize_url(): void
    {
        $url = $this->getJson('/api/auth/github/start')->assertOk()->json('auth_url');

        $this->assertStringStartsWith('https://github.com/login/oauth/authorize', $url);
        $this->assertStringContainsString('state=', $url);
    }

    public function test_callback_creates_user_with_verified_email(): void
    {
        $this->fakeGithubUser(['email' => 'Octo@Example.com']);

        $this->postCallback()
            ->assertCreated()
            ->assertJsonPath('email', 'octo@example.com')
            ->assertJsonPath('role', 'user')
            ->assertJsonPath('auth_method', 'github')
            ->assertJsonPath('github_login', 'octocat');

        $this->assertAuthenticated();
    }

    public function test_callback_without_email_uses_noreply_and_does_not_link(): void
    {
        $existing = User::factory()->create(['email' => 'octocat@users.noreply.github.com']);
        $this->fakeGithubUser(['email' => null]);

        // No verified email: must not match an existing account by email.
        $this->postCallback()->assertStatus(409);
        $this->assertGuest();
        $this->assertNull($existing->fresh()->github_id);
    }

    public function test_callback_links_existing_account_by_github_id(): void
    {
        $user = User::factory()->create(['email' => 'someone@example.com', 'github_id' => 4242]);
        $this->fakeGithubUser(['email' => 'different@example.com']);

        $this->postCallback()->assertOk()->assertJsonPath('id', (string) $user->id);
        $this->assertSame(1, User::count());
    }

    public function test_callback_never_grants_admin(): void
    {
        $this->fakeGithubUser(['email' => config('atglance.admin_email')]);

        $this->postCallback()->assertCreated()->assertJsonPath('role', 'user');
    }

    public function test_callback_rejects_invalid_state(): void
    {
        Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

        $this->postCallback()->assertStatus(400);
        $this->assertGuest();
    }
}
