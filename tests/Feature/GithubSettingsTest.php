<?php

namespace Tests\Feature;

use App\Filament\Pages\GithubSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\GithubOAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GithubSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_env_credentials_enable_github(): void
    {
        $this->getJson('/api/auth/providers')->assertOk()->assertJson(['github' => true]);
    }

    public function test_github_disabled_without_credentials(): void
    {
        config(['services.github.client_id' => null, 'services.github.client_secret' => null]);

        $this->getJson('/api/auth/providers')->assertJson(['github' => false]);
        $this->getJson('/api/auth/github/start')->assertStatus(503);
        $this->withHeaders(['Accept' => 'text/html'])->get('/admin/login')->assertDontSee('Sign in with GitHub');
    }

    public function test_admin_login_page_shows_github_button(): void
    {
        $this->withHeaders(['Accept' => 'text/html'])
            ->get('/admin/login')
            ->assertOk()
            ->assertSee('Sign in with GitHub')
            ->assertSee(route('admin.auth.github'), false);
    }

    public function test_admin_github_route_redirects_to_github(): void
    {
        $res = $this->withHeaders(['Accept' => 'text/html'])->get('/admin/auth/github')->assertRedirect();

        $this->assertStringStartsWith('https://github.com/login/oauth/authorize', $res->headers->get('Location'));
        $this->assertStringContainsString(urlencode(GithubOAuth::defaultRedirect()), $res->headers->get('Location'));
    }

    public function test_admin_saves_settings_with_encrypted_secret(): void
    {
        config(['services.github.client_id' => null, 'services.github.client_secret' => null]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(GithubSettings::class)
            ->set('data.enabled', true)
            ->set('data.client_id', 'db-client')
            ->set('data.client_secret', 'db-secret')
            ->set('data.redirect', 'http://localhost:3000/auth/sso/github/callback')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('data.client_secret', null);

        $stored = Setting::get(Setting::GITHUB);
        $this->assertNotSame('db-secret', $stored['client_secret']);

        $creds = GithubOAuth::credentials();
        $this->assertTrue($creds['enabled']);
        $this->assertSame('db-client', $creds['client_id']);
        $this->assertSame('db-secret', $creds['client_secret']);

        // Saving again with an empty secret keeps the stored one.
        Livewire::test(GithubSettings::class)
            ->set('data.client_id', 'db-client-2')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('db-secret', GithubOAuth::credentials()['client_secret']);
        $this->assertSame('db-client-2', GithubOAuth::credentials()['client_id']);
    }

    public function test_disabling_in_admin_turns_github_off(): void
    {
        Setting::put(Setting::GITHUB, ['enabled' => false]);

        $this->getJson('/api/auth/providers')->assertJson(['github' => false]);
    }

    public function test_settings_page_requires_admin(): void
    {
        $this->actingAs(User::factory()->create())
            ->withHeaders(['Accept' => 'text/html'])
            ->get('/admin/github-settings')
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->withHeaders(['Accept' => 'text/html'])
            ->get('/admin/github-settings')
            ->assertOk()
            ->assertSee('Authorization callback URL');
    }
}
