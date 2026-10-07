<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/** Sign up, sign in, sign out and password reset through the Blade forms. */
class WebAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Plain browser requests (the base TestCase sends JSON headers for API tests).
        $this->flushHeaders();
        $this->withoutVite();
    }

    public function test_auth_pages_render_for_guests(): void
    {
        foreach (['/login', '/register', '/forgot-password'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/reset-password')->assertOk()->assertSee('Reset link incomplete');
        $this->get('/reset-password?token=abc&email=a@b.co')->assertOk()->assertSee('For a@b.co');
    }

    public function test_register_signs_in_and_sends_verification(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Jane',
            'email' => ' Jane@Example.com ',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect('/dashboard');

        $user = User::firstWhere('email', 'jane@example.com');
        $this->assertNotNull($user);
        $this->assertSame('user', $user->role);
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_register_validates(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->from('/register')->post('/register', [
            'email' => 'taken@example.com', 'password' => 'secret123', 'password_confirmation' => 'other',
        ])->assertRedirect('/register')->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_registering_with_the_admin_email_does_not_grant_admin(): void
    {
        config(['atglance.admin_email' => 'boss@example.com']);

        $this->post('/register', ['email' => 'boss@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123']);

        $this->assertSame('user', User::firstWhere('email', 'boss@example.com')->role);
    }

    public function test_login_and_logout(): void
    {
        $user = User::factory()->create(['email' => 'ops@example.com', 'password' => 'secret123']);

        $this->from('/login')->post('/login', ['email' => 'ops@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'OPS@example.com', 'password' => 'secret123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        // Signed-in users skip the login page.
        $this->get('/login')->assertRedirect('/dashboard');

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_admin_lands_in_admin_panel(): void
    {
        User::factory()->admin()->create(['email' => 'root@example.com', 'password' => 'secret123']);

        $this->post('/login', ['email' => 'root@example.com', 'password' => 'secret123'])->assertRedirect('/admin');
    }

    public function test_dashboard_requires_login(): void
    {
        foreach (['/dashboard', '/dashboard/install', '/dashboard/licences', '/dashboard/profile'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_forgot_and_reset_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ops@example.com', 'password' => 'oldpass1']);

        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'ops@example.com'])
            ->assertRedirect('/forgot-password')->assertSessionHas('sent');
        // Same answer for unknown emails.
        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHas('sent');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->post('/reset-password', [
            'token' => $token, 'email' => 'ops@example.com', 'password' => 'newpass1', 'password_confirmation' => 'newpass1',
        ])->assertRedirect('/login')->assertSessionHas('status');

        $this->post('/login', ['email' => 'ops@example.com', 'password' => 'newpass1'])->assertRedirect('/dashboard');
    }

    public function test_reset_with_bad_token_fails(): void
    {
        User::factory()->create(['email' => 'ops@example.com']);

        $this->from('/reset-password')->post('/reset-password', [
            'token' => 'nope', 'email' => 'ops@example.com', 'password' => 'newpass1', 'password_confirmation' => 'newpass1',
        ])->assertSessionHasErrors('token');
    }

    public function test_resend_verification(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->from('/dashboard')->post('/dashboard/email/resend')
            ->assertRedirect('/dashboard')->assertSessionHas('status');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_reset_link_in_email_points_at_the_reset_page(): void
    {
        $user = User::factory()->create(['email' => 'ops@example.com']);
        $token = Password::createToken($user);

        $url = (new ResetPassword($token))->toMail($user)->actionUrl;

        $this->assertStringContainsString('/reset-password?token='.$token, $url);
    }
}
