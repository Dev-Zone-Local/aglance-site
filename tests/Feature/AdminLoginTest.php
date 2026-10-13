<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** The /admin/login page: signs admins in and says why when it cannot. */
class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flushHeaders();
        $this->withoutVite();
        Filament::setCurrentPanel('admin');
    }

    private function login(string $email, string $password)
    {
        return Livewire::test(Login::class)
            ->fillForm(['email' => $email, 'password' => $password])
            ->call('authenticate');
    }

    public function test_admin_signs_in_and_lands_in_the_panel(): void
    {
        User::factory()->admin()->create(['email' => 'root@example.com', 'password' => 'secret123']);

        $this->login('root@example.com', 'secret123')->assertHasNoFormErrors()->assertRedirect('/admin');
        $this->assertAuthenticated();
    }

    public function test_credentials_come_from_the_database_not_env(): void
    {
        User::factory()->admin()->create(['email' => 'root@example.com', 'password' => 'db-password']);

        // Old installs still have these in .env; they must not matter any more, even after a re-seed.
        putenv('ADMIN_EMAIL=root@example.com');
        putenv('ADMIN_PASSWORD=env-password');
        $_ENV['ADMIN_PASSWORD'] = $_SERVER['ADMIN_PASSWORD'] = 'env-password';
        $this->seed();

        try {
            $this->login('root@example.com', 'env-password')->assertHasFormErrors(['email']);
            $this->assertGuest();

            $this->login('root@example.com', 'db-password')->assertHasNoFormErrors()->assertRedirect('/admin');
            $this->assertAuthenticated();
        } finally {
            putenv('ADMIN_EMAIL');
            putenv('ADMIN_PASSWORD');
            unset($_ENV['ADMIN_PASSWORD'], $_SERVER['ADMIN_PASSWORD']);
        }
    }

    public function test_wrong_password_says_so(): void
    {
        User::factory()->admin()->create(['email' => 'root@example.com', 'password' => 'secret123']);

        $this->login('root@example.com', 'nope')->assertHasFormErrors(['email']);
        $this->assertGuest();
    }

    public function test_non_admin_account_is_told_it_has_no_admin_access(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => 'secret123']);

        $this->login('jane@example.com', 'secret123')
            ->assertHasFormErrors(['email'])
            ->assertSee('This account does not have admin access');
        $this->assertGuest();
    }

    public function test_intended_page_outside_the_panel_is_ignored(): void
    {
        User::factory()->admin()->create(['email' => 'root@example.com', 'password' => 'secret123']);

        // e.g. set by visiting /contact or /dashboard as a guest before opening /admin/login.
        session(['url.intended' => url('/contact#contact-form')]);
        $this->login('root@example.com', 'secret123')->assertRedirect('/admin');
    }

    public function test_intended_page_inside_the_panel_is_kept(): void
    {
        User::factory()->admin()->create(['email' => 'root@example.com', 'password' => 'secret123']);

        session(['url.intended' => url('/admin/messages')]);
        $this->login('root@example.com', 'secret123')->assertRedirect(url('/admin/messages'));
    }

    public function test_successful_sign_ins_do_not_count_towards_the_limit(): void
    {
        User::factory()->admin()->create(['email' => 'root@example.com', 'password' => 'secret123']);

        for ($i = 0; $i < 7; $i++) {
            $this->login('root@example.com', 'secret123')->assertHasNoFormErrors()->assertRedirect('/admin');
            auth()->logout();
        }
    }

    public function test_too_many_failed_attempts_are_throttled_with_a_reason(): void
    {
        User::factory()->admin()->create(['email' => 'root@example.com', 'password' => 'secret123']);

        for ($i = 0; $i < 5; $i++) {
            $this->login('root@example.com', 'wrong');
        }

        $this->login('root@example.com', 'secret123')->assertNotified()->assertNoRedirect();
        $this->assertGuest();
    }

    private function fakeCaptcha(bool $googleSays): void
    {
        $this->enableCaptcha();
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response(['success' => $googleSays, 'score' => 0.9, 'action' => 'admin_login'])]);
    }

    public function test_captcha_widget_shows_only_when_keys_are_set(): void
    {
        $this->get('/admin/login')->assertOk()->assertDontSee('recaptcha/api.js', false);

        $this->fakeCaptcha(true);
        $this->get('/admin/login')->assertOk()
            ->assertSee('https://www.google.com/recaptcha/api.js?render=site-key-123', false)
            ->assertSee("action: 'admin_login'", false);
    }

    public function test_captcha_is_required_when_enabled(): void
    {
        $this->fakeCaptcha(true);
        User::factory()->admin()->create(['email' => 'root@example.com', 'password' => 'secret123']);

        $this->login('root@example.com', 'secret123')
            ->assertHasErrors(['captcha'])
            ->assertSee('The security check did not load');
        $this->assertGuest();
    }

    public function test_bad_captcha_token_is_rejected(): void
    {
        $this->fakeCaptcha(false);
        User::factory()->admin()->create(['email' => 'root@example.com', 'password' => 'secret123']);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'root@example.com', 'password' => 'secret123'])
            ->set('captcha', 'bad-token')
            ->call('authenticate')
            ->assertHasErrors(['captcha'])
            ->assertSet('captcha', null);
        $this->assertGuest();
    }

    public function test_good_captcha_token_signs_in(): void
    {
        $this->fakeCaptcha(true);
        User::factory()->admin()->create(['email' => 'root@example.com', 'password' => 'secret123']);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'root@example.com', 'password' => 'secret123'])
            ->set('captcha', 'good-token')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect('/admin');
        $this->assertAuthenticated();
        Http::assertSent(fn ($r) => $r['secret'] === 'secret-456' && $r['response'] === 'good-token');
    }

    public function test_wrong_password_resets_the_captcha(): void
    {
        $this->fakeCaptcha(true);
        User::factory()->admin()->create(['email' => 'root@example.com', 'password' => 'secret123']);

        // Google accepts a token once; the page fetches a new one on the next try.
        Livewire::test(Login::class)
            ->fillForm(['email' => 'root@example.com', 'password' => 'wrong'])
            ->set('captcha', 'good-token')
            ->call('authenticate')
            ->assertHasFormErrors(['email'])
            ->assertSet('captcha', null);
    }

    public function test_login_page_explains_expired_session_and_broken_scripts(): void
    {
        $this->get('/admin/login')->assertOk()
            ->assertSee('id="ag-login-problem"', false)
            ->assertDontSee('Your session expired');

        $this->get('/admin/login?expired=1')->assertOk()->assertSee('Your session expired');
    }
}
