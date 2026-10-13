<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\User;
use App\Notifications\LicenseCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConsoleLicenseTest extends TestCase
{
    use RefreshDatabase;

    private const ABILITY = 'console:license';

    private const CONSOLE_A = 'console-aaaa-1111';

    private const CONSOLE_B = 'console-bbbb-2222';

    /** Console requests come from a server, not the SPA: no Origin/Referer, no session. */
    private function console(?string $key)
    {
        $this->flushHeaders();
        app('auth')->forgetGuards();

        return $this->withHeaders(array_filter([
            'Accept' => 'application/json',
            'Authorization' => $key ? "Bearer {$key}" : null,
        ]));
    }

    /** A licence key; "verified" means the user entered the emailed code. */
    private function newKey(?User $user = null, bool $verified = true): string
    {
        $new = ($user ?? User::factory()->create())->createToken('prod', [self::ABILITY]);

        if ($verified) {
            License::whereKey($new->accessToken->id)->update(['confirmed_at' => now()]);
        }

        // Users only ever see the key without Sanctum's "<id>|" prefix.
        return Str::after($new->plainTextToken, '|');
    }

    public function test_dashboard_key_works_for_console_and_legacy_prefixed_key_too(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $created = $this->actingAs($user)->postJson('/api/licenses', ['name' => 'prod'])->assertCreated()->json();

        $code = null;
        Notification::assertSentTo($user, LicenseCodeNotification::class, function ($n) use (&$code) {
            $code = $n->code;

            return true;
        });
        $this->postJson("/api/licenses/{$created['id']}/confirm", ['code' => $code])->assertOk();

        $this->assertStringNotContainsString('|', $created['key']);

        $this->console($created['key'])->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => self::CONSOLE_A])->assertCreated();

        // Keys handed out earlier as "<id>|atg_..." keep working.
        $this->console("{$created['id']}|{$created['key']}")
            ->postJson('/api/licenses/heartbeat', ['instance_id' => self::CONSOLE_A])
            ->assertOk();
    }

    public function test_licence_without_emailed_code_is_rejected(): void
    {
        $key = $this->newKey(verified: false);

        $this->console($key)->postJson('/api/licenses/verify')
            ->assertForbidden()
            ->assertJsonPath('valid', false)
            ->assertJsonPath('status', 'unverified');

        $this->console($key)->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => self::CONSOLE_A])
            ->assertForbidden()
            ->assertJsonPath('status', 'unverified');

        $this->assertSame(0, LicenseActivation::count());
    }

    public function test_new_licence_expires_one_year_after_creation(): void
    {
        Notification::fake();
        $this->freezeTime();
        $user = User::factory()->create();

        $created = $this->actingAs($user)->postJson('/api/licenses', ['name' => 'prod'])->assertCreated()->json();

        $this->assertSame(now()->addYear()->toIso8601String(), $created['expires_at']);
        $this->assertFalse($created['expired']);
    }

    public function test_expired_licence_is_refused_with_expired_status(): void
    {
        $key = $this->newKey();
        $this->console($key)->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => self::CONSOLE_A])->assertCreated();

        License::query()->update(['expires_at' => now()->subDay()]);

        foreach (['verify', 'activate', 'heartbeat'] as $endpoint) {
            $this->console($key)->postJson("/api/licenses/{$endpoint}", ['org_name' => 'Acme Corp', 'instance_id' => self::CONSOLE_A])
                ->assertForbidden()
                ->assertJsonPath('valid', false)
                ->assertJsonPath('status', 'expired');
        }
        $this->console($key)->putJson('/api/licenses/org', ['org_name' => 'New', 'instance_id' => self::CONSOLE_A])
            ->assertForbidden()
            ->assertJsonPath('status', 'expired');

        $this->assertSame('expired', License::first()->status());
    }

    public function test_licence_without_expiry_never_expires(): void
    {
        $key = $this->newKey();
        License::query()->update(['expires_at' => null]);

        $this->console($key)->postJson('/api/licenses/verify')->assertOk()->assertJsonPath('license.expires_at', null);
    }

    public function test_expired_licence_key_cannot_use_other_api_routes(): void
    {
        $key = $this->newKey();
        License::query()->update(['expires_at' => now()->subDay()]);

        // Accepted only so the licence API can say "expired"; dashboard routes stay closed.
        $this->console($key)->getJson('/api/licenses')->assertUnauthorized();
    }

    public function test_verified_licence_works_without_admin_approval(): void
    {
        $key = $this->newKey();

        $this->console($key)->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => self::CONSOLE_A])->assertCreated();
        $this->assertNull(License::first()->approved_at);
    }

    public function test_flagged_user_needs_admin_approval(): void
    {
        $user = User::factory()->create(['license_requires_approval' => true]);
        $key = $this->newKey($user);

        $this->console($key)->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => self::CONSOLE_A])
            ->assertForbidden()
            ->assertJsonPath('status', 'under_review');

        License::first()->approve(User::factory()->admin()->create());

        $this->console($key)->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => self::CONSOLE_A])->assertCreated();
    }

    public function test_verify_without_org_name_is_read_only(): void
    {
        $key = $this->newKey();
        $before = License::first()->only(['last_used_at', 'updated_at']);

        $this->console($key)->postJson('/api/licenses/verify', ['hostname' => 'ops-01'])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('status', 'available')
            ->assertJsonPath('console', null);

        $this->assertSame(0, LicenseActivation::count(), 'verify must not mark the licence in use');
        $this->assertSame(License::READY, License::first()->status());
        $this->assertEquals($before['updated_at'], License::first()->updated_at);
    }

    public function test_verify_with_org_name_marks_licence_in_use(): void
    {
        $key = $this->newKey();

        $this->console($key)->postJson('/api/licenses/verify', [
            'org_name' => '  Acme Corp ', 'instance_id' => self::CONSOLE_A, 'hostname' => 'ops-01', 'version' => '1.2.0',
        ])
            ->assertCreated()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('status', 'in_use')
            ->assertJsonPath('console.org_name', 'Acme Corp')
            ->assertJsonPath('console.version', '1.2.0');

        $this->assertSame(License::IN_USE, License::first()->status());

        // Read-only verify now reports who uses it; a second console is refused.
        $this->console($key)->postJson('/api/licenses/verify')
            ->assertOk()
            ->assertJsonPath('status', 'in_use')
            ->assertJsonPath('console.org_name', 'Acme Corp');
        $this->console($key)->postJson('/api/licenses/verify', ['org_name' => 'Other Org', 'instance_id' => self::CONSOLE_B])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This licence is already in use by another Management Console (organization: Acme Corp). Revoke it and create a new licence to move it.');
    }

    public function test_activate_requires_org_name(): void
    {
        $key = $this->newKey();

        $this->console($key)->postJson('/api/licenses/activate', ['instance_id' => self::CONSOLE_A])
            ->assertUnprocessable()
            ->assertJsonPath('errors.org_name.0', 'Send the organization name (org_name) to activate this licence.');

        $this->assertSame(0, LicenseActivation::count());
    }

    public function test_update_org_endpoint(): void
    {
        $key = $this->newKey();

        $this->console($key)->putJson('/api/licenses/org', ['org_name' => 'Acme', 'instance_id' => self::CONSOLE_A])
            ->assertStatus(409)
            ->assertJsonPath('status', 'not_activated');

        $this->console($key)->postJson('/api/licenses/activate', ['org_name' => 'Acme', 'instance_id' => self::CONSOLE_A])->assertCreated();

        $this->console($key)->putJson('/api/licenses/org', ['org_name' => 'Acme Group', 'instance_id' => self::CONSOLE_A])
            ->assertOk()
            ->assertJsonPath('status', 'in_use')
            ->assertJsonPath('console.org_name', 'Acme Group')
            ->assertJsonPath('previous_org_name', 'Acme');

        $this->assertSame('Acme Group', LicenseActivation::first()->org_name);

        // Only the owning console may rename; org_name is required.
        $this->console($key)->putJson('/api/licenses/org', ['org_name' => 'Hijack', 'instance_id' => self::CONSOLE_B])
            ->assertStatus(409)
            ->assertJsonPath('status', 'in_use_elsewhere');
        $this->console($key)->putJson('/api/licenses/org', ['instance_id' => self::CONSOLE_A])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('org_name');
        $this->console('atg_bad')->putJson('/api/licenses/org', ['org_name' => 'X'])->assertUnauthorized();

        $this->assertSame('Acme Group', LicenseActivation::first()->org_name);
    }

    public function test_org_name_can_change_on_same_console(): void
    {
        $key = $this->newKey();
        $this->console($key)->postJson('/api/licenses/activate', ['org_name' => 'Acme', 'instance_id' => self::CONSOLE_A])->assertCreated();
        $this->console($key)->postJson('/api/licenses/heartbeat', ['org_name' => 'Acme Inc', 'instance_id' => self::CONSOLE_A])->assertOk();

        $this->assertSame('Acme Inc', LicenseActivation::first()->org_name);
    }

    public function test_console_identity_falls_back_to_hostname_or_ip(): void
    {
        $byHost = $this->newKey();
        $this->console($byHost)->postJson('/api/licenses/verify', ['org_name' => 'Acme', 'hostname' => 'ops-01'])
            ->assertCreated()
            ->assertJsonPath('console.instance_id', 'host:ops-01');
        $this->console($byHost)->postJson('/api/licenses/verify', ['org_name' => 'Acme', 'hostname' => 'ops-02'])->assertStatus(409);

        $byIp = $this->newKey();
        $this->console($byIp)->postJson('/api/licenses/verify', ['org_name' => 'Acme'])
            ->assertCreated()
            ->assertJsonPath('console.instance_id', 'ip:127.0.0.1');
        $this->console($byIp)->postJson('/api/licenses/verify', ['org_name' => 'Acme'])->assertOk();
        $this->console($byIp)->postJson('/api/licenses/heartbeat')->assertOk();
    }

    public function test_same_console_can_activate_again(): void
    {
        $key = $this->newKey();

        $this->console($key)->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => self::CONSOLE_A])->assertCreated();
        $this->console($key)->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => self::CONSOLE_A, 'version' => '1.3.0'])
            ->assertOk()
            ->assertJsonPath('console.version', '1.3.0');

        $this->assertSame(1, LicenseActivation::count());
    }

    public function test_licence_works_on_only_one_console(): void
    {
        $key = $this->newKey();

        $this->console($key)->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => self::CONSOLE_A, 'hostname' => 'ops-01'])->assertCreated();

        $this->console($key)->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => self::CONSOLE_B])
            ->assertStatus(409)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('status', 'in_use_elsewhere')
            ->assertJsonPath('message', 'This licence is already in use by another Management Console (organization: Acme Corp). Revoke it and create a new licence to move it.');

        $this->assertSame(self::CONSOLE_A, LicenseActivation::first()->instance_id);
    }

    public function test_heartbeat_records_last_seen(): void
    {
        $key = $this->newKey();
        $this->console($key)->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => self::CONSOLE_A])->assertCreated();

        $this->travel(3)->hours();

        $this->console($key)->postJson('/api/licenses/heartbeat', ['instance_id' => self::CONSOLE_A, 'version' => '1.4.0'])
            ->assertOk()
            ->assertJsonPath('status', 'in_use');

        $activation = LicenseActivation::first();
        $this->assertTrue($activation->last_seen_at->isAfter($activation->activated_at->addHours(2)));
        $this->assertSame('1.4.0', $activation->console_version);
    }

    public function test_heartbeat_rejects_other_console_and_unactivated_licence(): void
    {
        $key = $this->newKey();

        $this->console($key)->postJson('/api/licenses/heartbeat', ['instance_id' => self::CONSOLE_A])
            ->assertStatus(409)
            ->assertJsonPath('status', 'not_activated');

        $this->console($key)->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => self::CONSOLE_A])->assertCreated();

        $this->console($key)->postJson('/api/licenses/heartbeat', ['instance_id' => self::CONSOLE_B])
            ->assertStatus(409)
            ->assertJsonPath('status', 'in_use_elsewhere');
    }

    public function test_revoked_licence_stops_working_and_frees_activation(): void
    {
        $user = User::factory()->create();
        $key = $this->newKey($user);
        $this->console($key)->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => self::CONSOLE_A])->assertCreated();

        $user->tokens()->delete();

        $this->assertSame(0, LicenseActivation::count(), 'activation row is removed with the licence');
        $this->console($key)->postJson('/api/licenses/heartbeat', ['instance_id' => self::CONSOLE_A])->assertUnauthorized();
    }

    public function test_validation_and_auth(): void
    {
        $key = $this->newKey();

        $this->console($key)->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => 'short'])->assertUnprocessable()->assertJsonValidationErrors('instance_id');
        $this->console($key)->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => 'bad id with spaces'])->assertUnprocessable();

        $this->console('atg_bad')->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => self::CONSOLE_A])->assertUnauthorized();
        $this->console(null)->postJson('/api/licenses/heartbeat', ['instance_id' => self::CONSOLE_A])->assertUnauthorized();

        $other = User::factory()->create()->createToken('x', ['something:else'])->plainTextToken;
        $this->console($other)->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => self::CONSOLE_A])
            ->assertUnauthorized()
            ->assertJsonPath('valid', false);
    }

    public function test_browser_session_cannot_activate(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => self::CONSOLE_A])
            ->assertUnauthorized();
    }

    public function test_dashboard_shows_in_use_and_last_seen(): void
    {
        $user = User::factory()->create();
        $key = $this->newKey($user);
        $this->console($key)->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => self::CONSOLE_A, 'hostname' => 'ops-01'])->assertCreated();

        $this->flushHeaders();
        $this->withHeaders(['Origin' => 'http://localhost:3000', 'Referer' => 'http://localhost:3000/', 'Accept' => 'application/json']);

        $this->actingAs($user)->getJson('/api/licenses')
            ->assertOk()
            ->assertJsonPath('licenses.0.in_use', true)
            ->assertJsonPath('licenses.0.console.org_name', 'Acme Corp')
            ->assertJsonStructure(['licenses' => [['console' => ['instance_id', 'hostname', 'version', 'activated_at', 'last_seen_at']]]]);
    }
}
