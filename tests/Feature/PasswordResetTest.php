<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC = 'If an account exists for that email, we sent a link to reset your password.';

    private function requestLink(User $user): string
    {
        Notification::fake();
        $this->postJson('/api/auth/forgot-password', ['email' => strtoupper($user->email)])
            ->assertOk()
            ->assertJsonPath('message', self::GENERIC);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token, $user) {
            $token = $n->token;
            $url = $n->toMail($user)->actionUrl;

            // The email links to the SPA page, not a Laravel route.
            return str_starts_with($url, 'http://localhost:3000/reset-password?token=')
                && str_contains($url, 'email='.urlencode($user->email));
        });

        return $token;
    }

    public function test_reset_flow(): void
    {
        $user = User::factory()->unverified()->create(['password' => 'old-password']);
        $token = $this->requestLink($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk()->assertJsonPath('message', 'Your password has been reset. You can sign in now.');

        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertTrue($user->hasVerifiedEmail(), 'the emailed link proves ownership');

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'new-password'])->assertOk();
    }

    public function test_link_works_only_once(): void
    {
        $user = User::factory()->create();
        $token = $this->requestLink($user);
        $body = ['token' => $token, 'email' => $user->email, 'password' => 'new-password', 'password_confirmation' => 'new-password'];

        $this->postJson('/api/auth/reset-password', $body)->assertOk();
        $this->postJson('/api/auth/reset-password', $body)
            ->assertUnprocessable()
            ->assertJsonPath('errors.token.0', 'This reset link is invalid or has expired. Request a new one.');
    }

    public function test_expired_link_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        $token = $this->requestLink($user);

        $this->travel(61)->minutes();

        $this->postJson('/api/auth/reset-password', [
            'token' => $token, 'email' => $user->email, 'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('token');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_wrong_token_and_mismatched_passwords(): void
    {
        $user = User::factory()->create();
        $token = $this->requestLink($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => 'bogus', 'email' => $user->email, 'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('token');

        $this->postJson('/api/auth/reset-password', [
            'token' => $token, 'email' => $user->email, 'password' => 'new-password', 'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonPath('errors.password.0', 'Passwords do not match');
    }

    public function test_unknown_email_gets_same_answer_and_no_mail(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonPath('message', self::GENERIC);

        $this->postJson('/api/auth/forgot-password', ['email' => 'octo@users.noreply.github.com'])
            ->assertOk()
            ->assertJsonPath('message', self::GENERIC);

        Notification::assertNothingSent();
    }

    public function test_github_user_without_password_can_set_one(): void
    {
        $user = User::factory()->create(['password' => null, 'auth_method' => 'github']);
        $token = $this->requestLink($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token, 'email' => $user->email, 'password' => 'brand-new', 'password_confirmation' => 'brand-new',
        ])->assertOk();

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'brand-new'])->assertOk();
    }
}
