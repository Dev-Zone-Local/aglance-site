<?php

namespace Tests\Feature;

use App\Filament\Pages\GoogleSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\GoogleOAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use Tests\TestCase;

/** "Sign in with Google" on the website, configured under Settings → Google login. */
class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flushHeaders();
        $this->withoutVite();
    }

    private function fakeGoogleUser(array $attrs = [], bool $verified = true): void
    {
        $attrs = array_merge(['id' => '1098765', 'name' => 'Asha Rao', 'email' => 'Asha@Example.com', 'avatar' => 'https://lh3.example/a.png'], $attrs);
        $google = (new SocialiteUser)->setRaw(['sub' => $attrs['id'], 'email_verified' => $verified])->map($attrs);

        Socialite::shouldReceive('driver->user')->andReturn($google);
    }

    public function test_button_and_routes_are_off_until_configured(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('Continue with Google');
        $this->get('/auth/google')->assertNotFound();

        $this->enableGoogleLogin();
        $this->get('/login')->assertOk()->assertSee('Continue with Google')->assertSee(route('auth.google'), false);
        $this->get('/register')->assertOk()->assertSee('Sign up with Google');
        $this->getJson('/api/auth/providers')->assertJson(['google' => true]);
    }

    public function test_redirect_goes_to_google(): void
    {
        $this->enableGoogleLogin();

        $location = $this->get('/auth/google')->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth', $location);
        $this->assertStringContainsString('client_id=google-client', $location);
        $this->assertStringContainsString(urlencode(GoogleOAuth::defaultRedirect()), $location);
    }

    public function test_callback_creates_a_verified_user(): void
    {
        Notification::fake();
        $this->enableGoogleLogin();
        $this->fakeGoogleUser();

        $this->get('/auth/sso/google/callback?code=abc&state=xyz')->assertRedirect(route('dashboard'));

        $user = User::sole();
        $this->assertSame('asha@example.com', $user->email);
        $this->assertSame('1098765', $user->google_id);
        $this->assertSame('google', $user->auth_method);
        $this->assertSame('user', $user->role);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertAuthenticatedAs($user);

        // Google already verified the address: no verification email.
        Notification::assertNothingSent();
    }

    public function test_long_google_avatar_url_is_stored(): void
    {
        // Google profile picture URLs can be ~1,500 characters; a VARCHAR(255) column made MySQL fail with a 500.
        $this->assertSame('text', Schema::getColumnType('users', 'avatar_url'));

        $this->enableGoogleLogin();
        $avatar = 'https://lh3.googleusercontent.com/a-/'.str_repeat('A', 1500).'=s96-c';
        $this->fakeGoogleUser(['avatar' => $avatar]);

        $this->get('/auth/sso/google/callback?code=abc&state=xyz')->assertRedirect(route('dashboard'));
        $this->assertSame($avatar, User::sole()->avatar_url);
    }

    public function test_callback_links_an_existing_account_by_verified_email(): void
    {
        $this->enableGoogleLogin();
        $existing = User::factory()->unverified()->create(['email' => 'asha@example.com', 'auth_method' => 'email']);
        $this->fakeGoogleUser();

        $this->get('/auth/sso/google/callback?code=abc&state=xyz')->assertRedirect();

        $existing->refresh();
        $this->assertSame('1098765', $existing->google_id);
        $this->assertSame('email', $existing->auth_method);
        $this->assertTrue($existing->hasVerifiedEmail());
        $this->assertSame(1, User::count());
    }

    public function test_unverified_google_email_is_refused(): void
    {
        $this->enableGoogleLogin();
        User::factory()->create(['email' => 'asha@example.com']);
        $this->fakeGoogleUser(verified: false);

        $this->get('/auth/sso/google/callback?code=abc&state=xyz')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
        $this->assertGuest();
        $this->assertNull(User::sole()->google_id);
    }

    public function test_google_never_grants_admin_but_admins_land_in_the_panel(): void
    {
        $this->enableGoogleLogin();

        $this->fakeGoogleUser(['email' => 'admin@example.com']);
        $this->get('/auth/sso/google/callback?code=abc&state=xyz');
        $this->assertSame('user', User::sole()->role);

        auth()->logout();
        User::sole()->forceFill(['role' => 'admin'])->save();
        $this->get('/auth/sso/google/callback?code=abc&state=xyz')->assertRedirect('/admin');
    }

    public function test_invalid_state_and_cancel_are_explained(): void
    {
        $this->enableGoogleLogin();

        $this->get('/auth/sso/google/callback?error=access_denied')->assertRedirect(route('login'))->assertSessionHas('error', 'Google sign-in was cancelled.');

        Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);
        $this->get('/auth/sso/google/callback?code=abc&state=bad')->assertSessionHas('error', 'Invalid OAuth state. Please start the sign-in again.');
        $this->assertGuest();
    }

    public function test_admin_saves_google_settings_with_encrypted_secret(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/google-settings')->assertOk()->assertSee('Google login')->assertSee(GoogleOAuth::defaultRedirect());

        Livewire::test(GoogleSettings::class)
            ->set('data.enabled', true)
            ->set('data.client_id', 'gid')
            ->set('data.client_secret', 'gsecret')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('data.client_secret', null);

        $this->assertNotSame('gsecret', Setting::get(Setting::GOOGLE)['client_secret']);
        $this->assertSame('gsecret', GoogleOAuth::credentials()['client_secret']);
        $this->assertTrue(GoogleOAuth::enabled());

        // An empty secret keeps the stored one; turning it on without a secret is refused.
        Livewire::test(GoogleSettings::class)->set('data.client_id', 'gid-2')->call('save')->assertHasNoErrors();
        $this->assertSame('gsecret', GoogleOAuth::credentials()['client_secret']);

        Setting::put(Setting::GOOGLE, []);
        Livewire::test(GoogleSettings::class)->set('data.enabled', true)->set('data.client_id', 'gid')->call('save')
            ->assertHasErrors(['data.client_secret']);
    }

    public function test_settings_page_requires_admin(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/google-settings')->assertForbidden();
    }
}
