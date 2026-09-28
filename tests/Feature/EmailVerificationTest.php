<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $overrides = [])
    {
        return $this->postJson('/api/auth/register', array_merge([
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides));
    }

    public function test_register_requires_matching_confirmation(): void
    {
        $this->register(['password_confirmation' => 'different'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'Passwords do not match');

        $this->register(['password_confirmation' => null])->assertUnprocessable();
    }

    public function test_register_sends_verification_email(): void
    {
        Notification::fake();

        $this->register()->assertCreated()->assertJsonPath('email_verified', false);

        Notification::assertSentTo(User::firstWhere('email', 'new@example.com'), VerifyEmail::class);
    }

    public function test_github_signup_sends_verification_to_github_email(): void
    {
        Notification::fake();
        Socialite::shouldReceive('driver->user')->andReturn((new SocialiteUser)->map([
            'id' => 99, 'nickname' => 'octo', 'name' => 'Octo', 'email' => 'Octo@Example.com', 'avatar' => null,
        ]));

        $this->postJson('/api/auth/github/callback?code=a&state=b')
            ->assertCreated()
            ->assertJsonPath('email', 'octo@example.com')
            ->assertJsonPath('email_verified', false);

        Notification::assertSentTo(User::firstWhere('email', 'octo@example.com'), VerifyEmail::class);
    }

    public function test_github_signup_without_email_sends_nothing(): void
    {
        Notification::fake();
        Socialite::shouldReceive('driver->user')->andReturn((new SocialiteUser)->map([
            'id' => 100, 'nickname' => 'ghost', 'name' => null, 'email' => null, 'avatar' => null,
        ]));

        $this->postJson('/api/auth/github/callback?code=a&state=b')
            ->assertCreated()
            ->assertJsonPath('email_deliverable', false);

        Notification::assertNothingSent();
    }

    public function test_link_in_email_verifies_and_redirects_to_dashboard(): void
    {
        Notification::fake();
        $this->register();
        $user = User::firstWhere('email', 'new@example.com');

        $url = null;
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $n) use ($user, &$url) {
            $url = $n->toMail($user)->actionUrl;

            return true;
        });

        $this->assertStringContainsString('/api/auth/email/verify/', $url);

        // Works without being logged in (e.g. opened on another device).
        $this->post('/api/auth/logout');
        $this->get($url)->assertRedirect('http://localhost:3000/dashboard?verified=1');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_tampered_or_expired_links_are_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $expired = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
        $this->get($expired)->assertRedirect('http://localhost:3000/dashboard?verified=expired');

        $wrongHash = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id, 'hash' => sha1('someone@else.com'),
        ]);
        $this->get($wrongHash)->assertRedirect('http://localhost:3000/dashboard?verified=invalid');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_verification(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/email/resend')->assertUnauthorized();

        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->postJson('/api/auth/email/resend')->assertOk();
        Notification::assertSentTo($user, VerifyEmail::class);

        $verified = User::factory()->create();
        $this->actingAs($verified)->postJson('/api/auth/email/resend')
            ->assertOk()
            ->assertJsonPath('message', 'Email already verified');
    }

    public function test_seeded_admin_is_verified(): void
    {
        $this->seed(AdminUserSeeder::class);

        $this->assertTrue(User::firstWhere('email', config('atglance.admin_email'))->hasVerifiedEmail());
    }
}
