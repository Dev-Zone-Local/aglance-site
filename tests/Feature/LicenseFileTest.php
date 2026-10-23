<?php

namespace Tests\Feature;

use App\Filament\Resources\LicenseFiles\Pages\ListLicenseFiles;
use App\Models\FeatureCatalog;
use App\Models\LicenseFile;
use App\Models\LicensePolicy;
use App\Models\User;
use App\Notifications\LicenseCodeNotification;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\LicenseFixtures;
use Tests\TestCase;

/**
 * Licence files (.atgl), the only kind of Management Console licence: POST /api/license-files
 * (and its email code), the dashboard form, the licence file limit and the admin release.
 */
class LicenseFileTest extends TestCase
{
    use RefreshDatabase;

    private const INSTALL = '3f2b8c1e-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        LicenseFixtures::configure();
        Notification::fake();
    }

    /** Requests the emailed code and returns it. */
    private function code(User $user): string
    {
        $this->actingAs($user)->postJson('/api/license-files/code')->assertOk();
        $code = null;
        Notification::assertSentTo($user, LicenseCodeNotification::class, function (LicenseCodeNotification $n) use (&$code) {
            $code = $n->code;

            return true;
        });

        return $code;
    }

    private function generate(User $user, array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->postJson('/api/license-files', $body + ['install_id' => self::INSTALL, 'code' => $this->code($user)]);
    }

    public function test_guests_cannot_generate(): void
    {
        $this->postJson('/api/license-files', ['install_id' => self::INSTALL])->assertUnauthorized();
        $this->withHeader('Accept', 'text/html')->post(route('dashboard.licences.file'))->assertRedirect(route('login'));
    }

    public function test_generates_a_signed_encrypted_file_after_the_emailed_code(): void
    {
        $user = User::factory()->create(['name' => 'Acme Corp', 'email' => 'ops@acme.test', 'plan' => 'enterprise']);

        $res = $this->generate($user)->assertOk();

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
        $this->assertSame('global', $p['policy_id']);
        $this->assertSame(FeatureCatalog::defaultKeys(), $p['features']);
        $this->assertSame(3 * 3600, strtotime($p['activate_before']) - strtotime($p['issued_at']));
        $this->assertSame(gmdate('Y-m-d\TH:i:s\Z', strtotime('+12 months', strtotime($p['issued_at']))), $p['expires_at']);
    }

    public function test_wrong_or_missing_code_is_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/license-files', ['install_id' => self::INSTALL])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->code($user);
        $this->actingAs($user)->postJson('/api/license-files', ['install_id' => self::INSTALL, 'code' => '00000'])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->assertSame(0, LicenseFile::count());
    }

    public function test_email_verification_can_be_turned_off_in_the_policy(): void
    {
        LicensePolicy::global()->update(['require_email_code' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/license-files', ['install_id' => self::INSTALL])->assertOk();
        Notification::assertNothingSent();
    }

    public function test_installation_id_required_or_optional_per_policy(): void
    {
        LicensePolicy::global()->update(['require_email_code' => false, 'install_id_mode' => 'required']);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/license-files')->assertStatus(422)->assertJsonValidationErrors('install_id');
        $this->actingAs($user)->postJson('/api/license-files', ['install_id' => 'not-a-uuid'])->assertStatus(422)->assertJsonValidationErrors('install_id');

        LicensePolicy::global()->update(['install_id_mode' => 'optional']);
        $res = $this->actingAs($user)->postJson('/api/license-files')->assertOk();
        $this->assertNull(LicenseFixtures::decode($res->getContent())['payload']['install_id']);
    }

    public function test_files_count_until_they_expire_so_the_limit_cannot_be_bypassed(): void
    {
        LicensePolicy::global()->update(['require_email_code' => false, 'max_files' => 1]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/license-files', ['install_id' => self::INSTALL])->assertOk();
        $this->actingAs($user)->postJson('/api/license-files', ['install_id' => '3f2b8c1e-0000-4000-8000-000000000002'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Your account can hold 1 active licence file. A file counts until it expires. To increase the limit, contact the AtGlance Support team.');

        // After the first file expires, the slot is free again.
        $this->travel(13)->months();
        $this->actingAs($user)->postJson('/api/license-files', ['install_id' => self::INSTALL])->assertOk();
    }

    public function test_admin_release_frees_a_slot_and_is_audited(): void
    {
        LicensePolicy::global()->update(['require_email_code' => false, 'max_files' => 1]);
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/license-files', ['install_id' => self::INSTALL])->assertOk();
        $file = LicenseFile::sole();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Livewire::test(ListLicenseFiles::class)->callAction(TestAction::make('release')->table($file));

        $this->assertNotNull($file->fresh()->released_at);
        $this->assertDatabaseHas('policy_audit_log', ['subject_type' => 'license_file', 'action' => 'released', 'admin_id' => $admin->id]);
        $this->actingAs($user)->postJson('/api/license-files', ['install_id' => self::INSTALL])->assertOk();
    }

    public function test_client_input_cannot_change_the_licence(): void
    {
        $user = User::factory()->create(['plan' => 'free']);

        $p = LicenseFixtures::decode($this->generate($user, [
            'plan' => 'enterprise', 'max_nodes' => 9999, 'features' => ['everything'], 'policy_type' => 'custom', 'expires_at' => '2099-01-01T00:00:00Z',
        ])->assertOk()->getContent())['payload'];

        $this->assertSame('free', $p['plan']);
        $this->assertSame(0, $p['max_nodes']);
        $this->assertSame('default', $p['policy_type']);
        $this->assertNotContains('everything', $p['features']);
    }

    public function test_blocked_accounts_cannot_generate(): void
    {
        LicensePolicy::global()->update(['require_email_code' => false]);
        $this->actingAs(User::factory()->create(['license_requires_approval' => true]))
            ->postJson('/api/license-files', ['install_id' => self::INSTALL])->assertForbidden();

        $this->assertSame(0, LicenseFile::count());
    }

    public function test_rate_limited_per_minute(): void
    {
        LicensePolicy::global()->update(['require_email_code' => false, 'max_files' => 50]);
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->postJson('/api/license-files', ['install_id' => self::INSTALL])->assertOk();
        }
        $this->actingAs($user)->postJson('/api/license-files', ['install_id' => self::INSTALL])->assertStatus(429);
    }

    public function test_missing_keys_give_503_without_details(): void
    {
        LicensePolicy::global()->update(['require_email_code' => false]);
        config(['atglance.license_file.signing_key' => null]);

        $this->actingAs(User::factory()->create())->postJson('/api/license-files', ['install_id' => self::INSTALL])
            ->assertStatus(503)
            ->assertJsonMissing(['message' => 'The signing key is not an Ed25519 private key in PKCS#8 form.']);
    }

    public function test_dashboard_form_downloads_the_file(): void
    {
        $user = User::factory()->create();
        $code = $this->code($user);

        $res = $this->actingAs($user)->withHeader('Accept', 'text/html')
            ->post(route('dashboard.licences.file'), ['install_id' => self::INSTALL, 'code' => $code])->assertOk();

        $res->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertSame($user->email, LicenseFixtures::decode($res->getContent())['payload']['email']);
    }

    public function test_dashboard_form_shows_errors_on_the_page(): void
    {
        LicensePolicy::global()->update(['require_email_code' => false]);
        $user = User::factory()->create(['license_requires_approval' => true]);

        $this->actingAs($user)->from(route('dashboard.licences'))
            ->withHeader('Accept', 'text/html')
            ->post(route('dashboard.licences.file'), ['install_id' => self::INSTALL])
            ->assertRedirect(route('dashboard.licences'))
            ->assertSessionHasErrors('license_file');
    }

    public function test_licences_page_shows_policy_files_and_limit(): void
    {
        LicensePolicy::global()->update(['require_email_code' => false, 'max_files' => 1]);
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/license-files', ['install_id' => self::INSTALL])->assertOk();
        $id = LicenseFile::sole()->license_id;

        $this->actingAs($user)->get(route('dashboard.licences'))
            ->assertOk()
            ->assertSee('1 / 1 active files')
            ->assertSee($id)
            ->assertSee('activation window: 180 min left')
            ->assertSee('To increase the limit, contact the AtGlance Support team')
            ->assertDontSee('licence key');

        $this->travel(181)->minutes();
        $this->get(route('dashboard.licences'))->assertSee('activation window: expired');
    }
}
