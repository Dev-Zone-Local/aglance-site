<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Google reCAPTCHA v2 on the email + password sign-in and sign-up forms. */
class RecaptchaTest extends TestCase
{
    use RefreshDatabase;

    private bool $googleSays = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flushHeaders();
        $this->withoutVite();
    }

    private function enable(bool $googleSays = true): void
    {
        $this->googleSays = $googleSays;
        config(['services.recaptcha.site_key' => 'site-key-123', 'services.recaptcha.secret_key' => 'secret-456']);
        Http::fake(['www.google.com/recaptcha/api/siteverify' => fn () => Http::response(['success' => $this->googleSays])]);
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

        foreach (['/login', '/register'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('data-sitekey="site-key-123"', false)
                ->assertSee('https://www.google.com/recaptcha/api.js', false);
        }
    }

    public function test_login_requires_a_valid_captcha(): void
    {
        $this->enable(googleSays: false);
        User::factory()->create(['email' => 'jane@example.com', 'password' => 'secret123']);
        $creds = ['email' => 'jane@example.com', 'password' => 'secret123'];

        $this->post('/login', $creds)->assertSessionHasErrors('g-recaptcha-response');
        $this->post('/login', [...$creds, 'g-recaptcha-response' => 'bad'])->assertSessionHasErrors('g-recaptcha-response');
        $this->assertGuest();

        $this->enable();
        $this->post('/login', [...$creds, 'g-recaptcha-response' => 'good'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        Http::assertSent(fn (Request $r) => $r['secret'] === 'secret-456' && $r['response'] === 'good');
    }

    public function test_register_requires_a_valid_captcha(): void
    {
        Notification::fake();
        $data = ['email' => 'new@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123'];

        $this->enable(googleSays: false);
        $this->post('/register', [...$data, 'g-recaptcha-response' => 'bad'])->assertSessionHasErrors('g-recaptcha-response');
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);

        $this->enable();
        $this->post('/register', [...$data, 'g-recaptcha-response' => 'good'])->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }
}
