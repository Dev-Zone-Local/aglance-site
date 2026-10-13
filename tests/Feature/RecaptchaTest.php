<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Google reCAPTCHA v3 on the email + password sign-in and sign-up forms. */
class RecaptchaTest extends TestCase
{
    use RefreshDatabase;

    private bool $googleSays = true;

    private float $score = 0.9;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flushHeaders();
        $this->withoutVite();
    }

    private function enable(bool $googleSays = true): void
    {
        $this->googleSays = $googleSays;
        $this->enableCaptcha();
        // Google echoes the action the page asked for; the test sends it as the token.
        Http::fake(['www.google.com/recaptcha/api/siteverify' => fn ($request) => Http::response([
            'success' => $this->googleSays, 'score' => $this->score, 'action' => explode(':', $request['response'])[0],
        ])]);
    }

    public function test_widget_is_hidden_while_keys_are_missing(): void
    {
        foreach (['/login', '/register'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('g-recaptcha', false);
        }
    }

    public function test_widget_is_shown_when_keys_are_set(): void
    {
        $this->enable();

        $this->get('/login')->assertOk()
            ->assertSee('https://www.google.com/recaptcha/api.js?render=site-key-123', false)
            ->assertSee('data-recaptcha-action="login"', false);
        $this->get('/register')->assertOk()->assertSee('data-recaptcha-action="register"', false);
    }

    public function test_login_requires_a_valid_captcha(): void
    {
        $this->enable(googleSays: false);
        User::factory()->create(['email' => 'jane@example.com', 'password' => 'secret123']);
        $creds = ['email' => 'jane@example.com', 'password' => 'secret123'];

        $this->post('/login', $creds)->assertSessionHasErrors('g-recaptcha-response');
        $this->post('/login', [...$creds, 'g-recaptcha-response' => 'login:bad'])->assertSessionHasErrors('g-recaptcha-response');
        $this->assertGuest();

        $this->enable();
        $this->post('/login', [...$creds, 'g-recaptcha-response' => 'login:good'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        Http::assertSent(fn (Request $r) => $r['secret'] === 'secret-456' && $r['response'] === 'login:good');
    }

    public function test_register_requires_a_valid_captcha(): void
    {
        Notification::fake();
        $data = ['email' => 'new@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123'];

        $this->enable(googleSays: false);
        $this->post('/register', [...$data, 'g-recaptcha-response' => 'register:bad'])->assertSessionHasErrors('g-recaptcha-response');
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);

        $this->enable();
        $this->post('/register', [...$data, 'g-recaptcha-response' => 'register:good'])->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_low_score_or_wrong_action_is_rejected(): void
    {
        $this->enable();
        User::factory()->create(['email' => 'jane@example.com', 'password' => 'secret123']);
        $creds = ['email' => 'jane@example.com', 'password' => 'secret123'];

        // Token made for another form.
        $this->post('/login', [...$creds, 'g-recaptcha-response' => 'register:x'])->assertSessionHasErrors('g-recaptcha-response');

        // Looks like a bot.
        $this->score = 0.2;
        $this->post('/login', [...$creds, 'g-recaptcha-response' => 'login:x'])->assertSessionHasErrors('g-recaptcha-response');
        $this->assertGuest();

        // Threshold comes from Settings → CAPTCHA.
        $this->enableCaptcha(minScore: 0.1);
        $this->post('/login', [...$creds, 'g-recaptcha-response' => 'login:x'])->assertRedirect(route('dashboard'));
    }
}
