<?php

namespace Tests\Feature;

use App\Models\FeatureCatalog;
use App\Models\LicenseFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LicenseFixtures;
use Tests\TestCase;

/**
 * Offline licence files (.atgl): POST /api/license/generate, the dashboard form, and the
 * list on the Licences page.
 */
class LicenseFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        LicenseFixtures::configure();
    }

    public function test_guests_cannot_generate(): void
    {
        $this->postJson('/api/license/generate')->assertUnauthorized();
        $this->withHeader('Accept', 'text/html')->post(route('dashboard.licences.file'))->assertRedirect(route('login'));
    }

    public function test_generates_a_signed_encrypted_file_from_the_account(): void
    {
        $user = User::factory()->create(['name' => 'Acme Corp', 'email' => 'ops@acme.test', 'plan' => 'enterprise']);

        $res = $this->actingAs($user)->post('/api/license/generate', [], ['Accept' => 'application/octet-stream'])->assertOk();

        $res->assertHeader('Content-Type', 'application/octet-stream');
        $file = LicenseFile::sole();
        $res->assertHeader('Content-Disposition', 'attachment; filename="atglance-'.$file->license_id.'.atgl"');

        $bytes = $res->getContent();
        $this->assertStringStartsWith("ATGL\x01", $bytes);
        $this->assertStringNotContainsString('Acme Corp', $bytes, 'The payload must be encrypted');

        $p = LicenseFixtures::decode($bytes)['payload'];
        $this->assertSame($file->license_id, $p['license_id']);
        $this->assertSame('atglance-console', $p['product']);
        $this->assertSame('test-2026', $p['key_id']);
        $this->assertSame('Acme Corp', $p['customer']);
        $this->assertSame('ops@acme.test', $p['email']);
        $this->assertSame('enterprise', $p['plan']);
        $this->assertNull($p['install_id']);
        $this->assertSame(2, $p['format_version']);
        $this->assertArrayNotHasKey('seats', $p);
        // The seeded global default (plan defaults inherit it): unlimited, 12 months, 3 hours, every feature.
        $this->assertSame(0, $p['max_nodes']);
        $this->assertSame(0, $p['max_users']);
        $this->assertSame('global', $p['policy_id']);
        $this->assertSame('default', $p['policy_type']);
        $this->assertSame(12, $p['validity_months']);
        $this->assertSame(FeatureCatalog::defaultKeys(), $p['features']);

        $issued = strtotime($p['issued_at']);
        $this->assertSame($issued + 3 * 3600, strtotime($p['activate_before']));
        $this->assertSame(gmdate('Y-m-d\TH:i:s\Z', strtotime('+12 months', $issued)), $p['expires_at']);
        $this->assertSame($p['activate_before'], $file->activate_before->utc()->format('Y-m-d\TH:i:s\Z'));
        $this->assertSame(['max_nodes' => 0, 'max_users' => 0, 'policy_type' => 'default', 'issued_by' => 'user'],
            $file->only(['max_nodes', 'max_users', 'policy_type', 'issued_by']));
    }

    public function test_client_input_cannot_change_the_licence(): void
    {
        $user = User::factory()->create(['plan' => 'free']);

        $res = $this->actingAs($user)->postJson('/api/license/generate', [
            'plan' => 'enterprise', 'max_nodes' => 9999, 'max_users' => 9999, 'features' => ['everything'],
            'policy_type' => 'custom', 'customer' => 'Someone else', 'expires_at' => '2099-01-01T00:00:00Z',
        ])->assertOk();

        $p = LicenseFixtures::decode($res->getContent())['payload'];
        $this->assertSame('free', $p['plan']);
        $this->assertSame(0, $p['max_nodes']);
        $this->assertSame('default', $p['policy_type']);
        $this->assertNotContains('everything', $p['features']);
        $this->assertSame($user->name, $p['customer']);
    }

    public function test_installation_id_binds_the_file(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson('/api/license/generate', ['install_id' => '3F2B8C1E-0000-4000-8000-000000000001'])->assertOk();

        $this->assertSame('3f2b8c1e-0000-4000-8000-000000000001', LicenseFixtures::decode($res->getContent())['payload']['install_id']);
        $this->assertSame('3f2b8c1e-0000-4000-8000-000000000001', LicenseFile::sole()->install_id);
    }

    public function test_invalid_installation_id_is_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/license/generate', ['install_id' => 'not-a-uuid'])
            ->assertStatus(422)->assertJsonValidationErrors('install_id');

        $this->assertSame(0, LicenseFile::count());
    }

    public function test_unverified_and_flagged_users_cannot_generate(): void
    {
        $this->actingAs(User::factory()->unverified()->create())->postJson('/api/license/generate')->assertForbidden();
        $this->actingAs(User::factory()->create(['license_requires_approval' => true]))->postJson('/api/license/generate')->assertForbidden();

        $this->assertSame(0, LicenseFile::count());
    }

    public function test_rate_limited_per_minute(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->postJson('/api/license/generate')->assertOk();
        }
        $this->actingAs($user)->postJson('/api/license/generate')->assertStatus(429);
    }

    public function test_daily_cap(): void
    {
        config(['atglance.license_file.max_per_day' => 2]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/license/generate')->assertOk();
        $this->actingAs($user)->postJson('/api/license/generate')->assertOk();
        $this->actingAs($user)->postJson('/api/license/generate')->assertStatus(429);
    }

    public function test_missing_keys_give_503_without_details(): void
    {
        config(['atglance.license_file.signing_key' => null]);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/license/generate')
            ->assertStatus(503)
            ->assertJsonMissing(['message' => 'The signing key is not an Ed25519 private key in PKCS#8 form.']);
    }

    public function test_dashboard_form_downloads_the_file(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->post(route('dashboard.licences.file'))->assertOk();

        $res->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertSame($user->email, LicenseFixtures::decode($res->getContent())['payload']['email']);
    }

    public function test_dashboard_form_shows_errors_on_the_page(): void
    {
        $user = User::factory()->create(['license_requires_approval' => true]);

        $this->actingAs($user)->from(route('dashboard.licences'))
            ->withHeader('Accept', 'text/html')
            ->post(route('dashboard.licences.file'))
            ->assertRedirect(route('dashboard.licences'))
            ->assertSessionHasErrors('license_file');
    }

    public function test_licences_page_lists_files_with_the_activation_window(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/license/generate')->assertOk();
        $id = LicenseFile::sole()->license_id;

        $this->get(route('dashboard.licences'))
            ->assertOk()
            ->assertSee('Generate licence file')
            ->assertSee('within 3 hours')
            ->assertSee($id)
            ->assertSee('activation window: 180 min left');

        $this->travel(2)->hours();
        $this->get(route('dashboard.licences'))->assertSee('activation window: 60 min left');

        $this->travel(61)->minutes();
        $this->get(route('dashboard.licences'))->assertSee('activation window: expired');
    }
}
