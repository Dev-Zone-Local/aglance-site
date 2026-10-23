<?php

namespace Tests\Feature;

use App\Models\FeatureCatalog;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseFile;
use App\Models\LicensePolicy;
use App\Models\User;
use App\Support\LicenseFile\Issuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LicenseFixtures;
use Tests\TestCase;

/**
 * Licence files (.atgl) of a licence: POST /api/licenses/{id}/file, the dashboard form, and the
 * file a console receives when it activates the licence key online.
 */
class LicenseFileTest extends TestCase
{
    use RefreshDatabase;

    private const INSTALL = '3f2b8c1e-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        LicenseFixtures::configure();
    }

    /** A confirmed (usable) licence of $user. */
    private function licence(User $user, string $name = 'prod-console'): License
    {
        $licence = License::findOrFail($user->createToken($name, [License::ABILITY], now()->addYear())->accessToken->id);
        $licence->confirm();

        return $licence->fresh();
    }

    private function download(User $user, License $licence, ?string $installId = self::INSTALL)
    {
        return $this->actingAs($user)->post("/api/licenses/{$licence->id}/file", array_filter(['install_id' => $installId]), ['Accept' => 'application/octet-stream']);
    }

    public function test_guests_cannot_download(): void
    {
        $licence = $this->licence(User::factory()->create());

        $this->postJson("/api/licenses/{$licence->id}/file", ['install_id' => self::INSTALL])->assertUnauthorized();
        $this->withHeader('Accept', 'text/html')->post(route('dashboard.licences.file', $licence->id))->assertRedirect(route('login'));
    }

    public function test_downloads_a_signed_encrypted_file_for_the_licence(): void
    {
        $user = User::factory()->create(['name' => 'Acme Corp', 'email' => 'ops@acme.test', 'plan' => 'enterprise']);
        $licence = $this->licence($user);

        $res = $this->download($user, $licence)->assertOk();

        $res->assertHeader('Content-Type', 'application/octet-stream');
        $file = LicenseFile::sole();
        $res->assertHeader('Content-Disposition', 'attachment; filename="atglance-'.$file->license_id.'.atgl"');
        $bytes = $res->getContent();
        $this->assertStringStartsWith("ATGL\x01", $bytes);
        $this->assertStringNotContainsString('Acme Corp', $bytes, 'The payload must be encrypted');

        $p = LicenseFixtures::decode($bytes)['payload'];
        $this->assertSame($file->license_id, $p['license_id']);
        $this->assertSame('Acme Corp', $p['customer']);
        $this->assertSame('enterprise', $p['plan']);
        $this->assertSame(2, $p['format_version']);
        $this->assertSame(self::INSTALL, $p['install_id']);
        $this->assertSame(0, $p['max_nodes']);
        $this->assertSame('global', $p['policy_id']);
        $this->assertSame(FeatureCatalog::defaultKeys(), $p['features']);
        // The file expires with its licence.
        $this->assertSame($licence->expires_at->utc()->format('Y-m-d\TH:i:s\Z'), $p['expires_at']);
        $this->assertSame(3 * 3600, strtotime($p['activate_before']) - strtotime($p['issued_at']));

        $this->assertSame($licence->id, $file->token_id);
        $this->assertSame('user', $file->issued_by);
        // The licence is now in use by that console.
        $this->assertSame(self::INSTALL, LicenseActivation::sole()->instance_id);
    }

    public function test_installation_id_is_required_when_the_policy_says_so(): void
    {
        LicensePolicy::global()->update(['install_id_mode' => 'required']);
        $user = User::factory()->create();
        $licence = $this->licence($user);

        $this->actingAs($user)->postJson("/api/licenses/{$licence->id}/file")->assertStatus(422)->assertJsonValidationErrors('install_id');
        $this->actingAs($user)->postJson("/api/licenses/{$licence->id}/file", ['install_id' => 'not-a-uuid'])->assertStatus(422)->assertJsonValidationErrors('install_id');
        $this->assertSame(0, LicenseFile::count());
    }

    public function test_installation_id_is_optional_by_default_and_the_licence_is_then_in_use(): void
    {
        $user = User::factory()->create();
        $licence = $this->licence($user);

        $this->actingAs($user)->postJson("/api/licenses/{$licence->id}/file")->assertOk();
        $this->assertNull(LicenseFile::sole()->install_id);
        $this->assertSame(Issuer::UNBOUND, $licence->fresh()->activation->instance_id);

        // In use: no bound file for a console, and no revoke until it expires.
        $this->actingAs($user)->postJson("/api/licenses/{$licence->id}/file", ['install_id' => '3f2b8c1e-0000-4000-8000-000000000002'])->assertStatus(409);
        $this->actingAs($user)->deleteJson("/api/licenses/{$licence->id}", ['password' => 'password'])->assertStatus(409);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $licence->id]);
    }

    public function test_a_used_licence_can_be_revoked_after_it_expires(): void
    {
        $user = User::factory()->create();
        $licence = $this->licence($user);
        $this->download($user, $licence)->assertOk();
        $this->actingAs($user)->deleteJson("/api/licenses/{$licence->id}", ['password' => 'password'])->assertStatus(409);

        $licence->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->actingAs($user)->deleteJson("/api/licenses/{$licence->id}", ['password' => 'password'])->assertOk();
    }

    public function test_one_licence_one_console(): void
    {
        $user = User::factory()->create();
        $licence = $this->licence($user);

        $this->download($user, $licence)->assertOk();
        $this->download($user, $licence)->assertOk(); // the same console may download again

        $this->actingAs($user)->postJson("/api/licenses/{$licence->id}/file", ['install_id' => '3f2b8c1e-0000-4000-8000-000000000002'])
            ->assertStatus(409);
        $this->assertSame(2, LicenseFile::count());
    }

    public function test_client_input_cannot_change_the_licence(): void
    {
        $user = User::factory()->create(['plan' => 'free']);
        $licence = $this->licence($user);

        $res = $this->actingAs($user)->postJson("/api/licenses/{$licence->id}/file", [
            'install_id' => self::INSTALL, 'plan' => 'enterprise', 'max_nodes' => 9999, 'features' => ['everything'],
            'policy_type' => 'custom', 'expires_at' => '2099-01-01T00:00:00Z',
        ])->assertOk();

        $p = LicenseFixtures::decode($res->getContent())['payload'];
        $this->assertSame('free', $p['plan']);
        $this->assertSame(0, $p['max_nodes']);
        $this->assertSame('default', $p['policy_type']);
        $this->assertNotContains('everything', $p['features']);
    }

    public function test_only_confirmed_and_approved_licences_of_the_user(): void
    {
        $user = User::factory()->create();
        $unconfirmed = License::findOrFail($user->createToken('new', [License::ABILITY], now()->addYear())->accessToken->id);
        $this->actingAs($user)->postJson("/api/licenses/{$unconfirmed->id}/file", ['install_id' => self::INSTALL])->assertForbidden();

        $flagged = User::factory()->create(['license_requires_approval' => true]);
        $this->actingAs($flagged)->postJson('/api/licenses/'.$this->licence($flagged)->id.'/file', ['install_id' => self::INSTALL])->assertForbidden();

        $other = User::factory()->create();
        $this->actingAs($other)->postJson('/api/licenses/'.$this->licence($user)->id.'/file', ['install_id' => self::INSTALL])->assertNotFound();

        $this->assertSame(0, LicenseFile::count());
    }

    public function test_rate_limited_per_minute(): void
    {
        $user = User::factory()->create();
        $licence = $this->licence($user);

        for ($i = 0; $i < 5; $i++) {
            $this->download($user, $licence)->assertOk();
        }
        $this->download($user, $licence)->assertStatus(429);
    }

    public function test_missing_keys_give_503_without_details(): void
    {
        config(['atglance.license_file.signing_key' => null]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/licenses/'.$this->licence($user)->id.'/file', ['install_id' => self::INSTALL])
            ->assertStatus(503)
            ->assertJsonMissing(['message' => 'The signing key is not an Ed25519 private key in PKCS#8 form.']);
    }

    public function test_dashboard_form_downloads_the_file(): void
    {
        $user = User::factory()->create();
        $licence = $this->licence($user);

        $res = $this->actingAs($user)->withHeader('Accept', 'text/html')
            ->post(route('dashboard.licences.file', $licence->id), ['install_id' => self::INSTALL])->assertOk();

        $res->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertSame($user->email, LicenseFixtures::decode($res->getContent())['payload']['email']);
    }

    public function test_dashboard_form_shows_errors_on_the_page(): void
    {
        $user = User::factory()->create();
        $licence = $this->licence($user);
        LicenseActivation::create(['token_id' => $licence->id, 'instance_id' => 'another-console-id', 'activated_at' => now(), 'last_seen_at' => now()]);

        $this->actingAs($user)->from(route('dashboard.licences'))
            ->withHeader('Accept', 'text/html')
            ->post(route('dashboard.licences.file', $licence->id), ['install_id' => self::INSTALL])
            ->assertRedirect(route('dashboard.licences'))
            ->assertSessionHasErrors('license_file');
    }

    public function test_licences_page_offers_key_and_file_and_lists_files(): void
    {
        $user = User::factory()->create();
        $licence = $this->licence($user);
        $this->download($user, $licence)->assertOk();
        $id = LicenseFile::sole()->license_id;

        $this->actingAs($user)->get(route('dashboard.licences'))
            ->assertOk()
            ->assertSee('Console with internet access')
            ->assertSee('Console without internet access')
            ->assertSee('Licence file')
            ->assertSee($id)
            ->assertSee('activation window: 180 min left')
            ->assertDontSee('up to version 2.2');

        $this->travel(181)->minutes();
        $this->get(route('dashboard.licences'))->assertSee('activation window: expired');
    }
}
