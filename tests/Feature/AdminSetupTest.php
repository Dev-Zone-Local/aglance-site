<?php

namespace Tests\Feature;

use App\Filament\Pages\CaptchaSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\Captcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/** Admin account and CAPTCHA are set up in the admin panel / with artisan, not in .env. */
class AdminSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flushHeaders();
        $this->withoutVite();
    }

    public function test_make_admin_creates_a_verified_admin(): void
    {
        $this->artisan('atglance:make-admin', ['email' => 'Boss@Example.com', '--password' => 'secret-123'])
            ->expectsOutputToContain('Created admin boss@example.com')
            ->assertSuccessful();

        $admin = User::sole();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertTrue(Hash::check('secret-123', $admin->password));
    }

    public function test_make_admin_asks_for_missing_values_and_promotes_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'ops@example.com', 'password' => 'old-password']);

        $this->artisan('atglance:make-admin')
            ->expectsQuestion('Admin email', 'ops@example.com')
            ->expectsQuestion('Password (at least 8 characters)', 'new-password')
            ->expectsOutputToContain('Updated admin ops@example.com')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->isAdmin());
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertSame(1, User::count());
    }

    public function test_make_admin_rejects_bad_input(): void
    {
        $this->artisan('atglance:make-admin', ['email' => 'not-an-email', '--password' => 'short'])->assertFailed();
        $this->assertSame(0, User::count());
    }

    public function test_seeding_needs_no_admin_settings(): void
    {
        $this->seed();
        $this->assertSame(0, User::where('role', 'admin')->count());
    }

    public function test_admin_saves_captcha_settings_with_encrypted_secret(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get('/admin/captcha-settings')->assertOk()->assertSee('reCAPTCHA v3');

        Livewire::test(CaptchaSettings::class)
            ->set('data.enabled', true)
            ->set('data.site_key', 'site-abc')
            ->set('data.secret_key', 'secret-xyz')
            ->set('data.min_score', 0.7)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('data.secret_key', null);

        $this->assertNotSame('secret-xyz', Setting::get(Setting::CAPTCHA)['secret_key']);
        $this->assertTrue(Captcha::enabled());
        $this->assertSame('secret-xyz', Captcha::secretKey());
        $this->assertSame(0.7, Captcha::minScore());

        // The page now shows the CAPTCHA on the website forms.
        auth()->logout();
        $this->get('/login')->assertSee('api.js?render=site-abc', false);

        // Empty secret keeps the stored one; a score outside 0..1 is refused.
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(CaptchaSettings::class)->set('data.site_key', 'site-def')->call('save')->assertHasNoErrors();
        $this->assertSame('secret-xyz', Captcha::secretKey());
        Livewire::test(CaptchaSettings::class)->set('data.min_score', 2)->call('save')->assertHasErrors(['data.min_score']);
    }

    public function test_captcha_off_command_unlocks_the_admin_login(): void
    {
        $this->enableCaptcha();
        $this->assertTrue(Captcha::enabled());

        $this->artisan('atglance:captcha', ['--off' => true])->assertSuccessful();

        $this->assertFalse(Captcha::enabled());
        $this->assertSame('site-key-123', Captcha::siteKey()); // keys kept for when it is turned back on
        $this->get('/admin/login')->assertDontSee('recaptcha/api.js', false);
    }

    public function test_settings_pages_require_admin(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/captcha-settings')->assertForbidden();
    }

    public function test_secret_unreadable_after_app_key_change_counts_as_missing(): void
    {
        Setting::put(Setting::CAPTCHA, ['enabled' => true, 'site_key' => 'k', 'secret_key' => 'not-encrypted-with-this-key']);
        $this->assertFalse(Captcha::enabled());

        Setting::put(Setting::CAPTCHA, ['enabled' => true, 'site_key' => 'k', 'secret_key' => Crypt::encryptString('s')]);
        $this->assertTrue(Captcha::enabled());
    }
}
