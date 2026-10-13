<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/** One welcome email per account, sent when the email address is verified. */
class WelcomeEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flushHeaders();
        $this->withoutVite();
        Notification::fake();
    }

    private function fakeGoogleUser(): void
    {
        $google = (new SocialiteUser)->setRaw(['sub' => '42', 'email_verified' => true])
            ->map(['id' => '42', 'name' => 'Asha Rao', 'email' => 'asha@example.com', 'avatar' => null]);
        Socialite::shouldReceive('driver->user')->andReturn($google);
    }

    public function test_google_sign_up_sends_welcome_once(): void
    {
        $this->enableGoogleLogin();
        $this->fakeGoogleUser();

        $this->get('/auth/sso/google/callback?code=a&state=b')->assertRedirect(route('dashboard'));
        $user = User::sole();

        Notification::assertSentToTimes($user, WelcomeNotification::class, 1);
        Notification::assertNotSentTo($user, VerifyEmail::class);
        $this->assertNotNull($user->welcomed_at);

        // Signing in with Google again does not send another one.
        auth()->logout();
        $this->get('/auth/sso/google/callback?code=a&state=b');
        Notification::assertSentToTimes($user, WelcomeNotification::class, 1);
    }

    public function test_email_sign_up_gets_welcome_after_verifying(): void
    {
        $this->post('/register', ['email' => 'new@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123']);
        $user = User::sole();

        // Not yet: only the verification link.
        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertNotSentTo($user, WelcomeNotification::class);

        $url = null;
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $n) use ($user, &$url) {
            $url = $n->toMail($user)->actionUrl;

            return true;
        });
        $this->get($url)->assertRedirect();

        Notification::assertSentToTimes($user, WelcomeNotification::class, 1);

        // Opening the link again does not send another one.
        $this->get($url);
        Notification::assertSentToTimes($user, WelcomeNotification::class, 1);
    }

    public function test_admin_activation_also_welcomes(): void
    {
        $user = User::factory()->unverified()->create();

        $user->markEmailAsVerified();

        Notification::assertSentToTimes($user, WelcomeNotification::class, 1);
    }

    public function test_never_twice_even_if_verification_is_reset(): void
    {
        $user = User::factory()->unverified()->create();
        $user->markEmailAsVerified();

        $user->forceFill(['email_verified_at' => null])->save();
        $user->markEmailAsVerified();

        Notification::assertSentToTimes($user, WelcomeNotification::class, 1);
    }

    public function test_accounts_created_verified_and_noreply_emails_get_nothing(): void
    {
        User::factory()->create(); // e.g. seeded or created by an admin
        $github = User::factory()->unverified()->create(['email' => 'octo@users.noreply.github.com']);
        $github->markEmailAsVerified();

        Notification::assertNothingSent();
    }

    public function test_mail_failure_does_not_break_verification(): void
    {
        Notification::swap(new class extends \Illuminate\Support\Testing\Fakes\NotificationFake
        {
            public function send($notifiables, $notification): void
            {
                throw new \RuntimeException('SMTP down');
            }
        });
        $user = User::factory()->unverified()->create();

        $user->markEmailAsVerified();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertNull($user->fresh()->welcomed_at, 'not marked as welcomed when sending failed');
    }

    public function test_welcome_email_content(): void
    {
        $user = User::factory()->make(['name' => 'Asha']);
        $mail = (new WelcomeNotification)->toMail($user);

        $this->assertSame('Welcome to AtGlance', $mail->subject);
        $this->assertSame('Hi Asha,', $mail->greeting);
        $this->assertStringEndsWith('/dashboard', $mail->actionUrl);
    }
}
