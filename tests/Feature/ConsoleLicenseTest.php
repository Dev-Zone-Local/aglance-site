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

        $this->console($created['key'])->postJson('/api/licenses/activate', ['instance_id' => self::CONSOLE_A])->assertCreated();

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

        $this->console($key)->postJson('/api/licenses/activate', ['instance_id' => self::CONSOLE_A])
            ->assertForbidden()
            ->assertJsonPath('status', 'unverified');

        $this->assertSame(0, LicenseActivation::count());
    }

    public function test_verified_licence_works_without_admin_approval(): void
    {
        $key = $this->newKey();

        $this->console($key)->postJson('/api/licenses/activate', ['instance_id' => self::CONSOLE_A])->assertCreated();
        $this->assertNull(License::first()->approved_at);
    }

    public function test_flagged_user_needs_admin_approval(): void
    {
        $user = User::factory()->create(['license_requires_approval' => true]);
        $key = $this->newKey($user);

        $this->console($key)->postJson('/api/licenses/activate', ['instance_id' => self::CONSOLE_A])
            ->assertForbidden()
            ->assertJsonPath('status', 'under_review');

        License::first()->approve(User::factory()->admin()->create());

        $this->console($key)->postJson('/api/licenses/activate', ['instance_id' => self::CONSOLE_A])->assertCreated();
    }

    public function test_verify_marks_licence_in_use(): void
    {
        $key = $this->newKey();

        $this->console($key)->postJson('/api/licenses/verify', [
            'instance_id' => self::CONSOLE_A, 'hostname' => 'ops-01', 'version' => '1.2.0',
        ])
            ->assertCreated()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('status', 'in_use')
            ->assertJsonPath('console.instance_id', self::CONSOLE_A)
            ->assertJsonPath('console.hostname', 'ops-01')
            ->assertJsonPath('console.version', '1.2.0');

        $this->assertSame(License::IN_USE, License::first()->status());

        // Verifying again from the same console is fine; another console is refused.
        $this->console($key)->postJson('/api/licenses/verify', ['instance_id' => self::CONSOLE_A])->assertOk();
        $this->console($key)->postJson('/api/licenses/verify', ['instance_id' => self::CONSOLE_B])->assertStatus(409);
    }

    public function test_verify_without_instance_id_binds_to_hostname_or_ip(): void
    {
        $byHost = $this->newKey();
        $this->console($byHost)->postJson('/api/licenses/verify', ['hostname' => 'ops-01'])
            ->assertCreated()
            ->assertJsonPath('console.instance_id', 'host:ops-01');
        $this->console($byHost)->postJson('/api/licenses/verify', ['hostname' => 'ops-02'])->assertStatus(409);

        $byIp = $this->newKey();
        $this->console($byIp)->postJson('/api/licenses/verify')
            ->assertCreated()
            ->assertJsonPath('console.instance_id', 'ip:127.0.0.1');
        $this->console($byIp)->postJson('/api/licenses/verify')->assertOk();
        $this->console($byIp)->postJson('/api/licenses/heartbeat')->assertOk();
    }

    public function test_same_console_can_activate_again(): void
    {
        $key = $this->newKey();

        $this->console($key)->postJson('/api/licenses/activate', ['instance_id' => self::CONSOLE_A])->assertCreated();
        $this->console($key)->postJson('/api/licenses/activate', ['instance_id' => self::CONSOLE_A, 'version' => '1.3.0'])
            ->assertOk()
            ->assertJsonPath('console.version', '1.3.0');

        $this->assertSame(1, LicenseActivation::count());
    }

    public function test_licence_works_on_only_one_console(): void
    {
        $key = $this->newKey();

        $this->console($key)->postJson('/api/licenses/activate', ['instance_id' => self::CONSOLE_A, 'hostname' => 'ops-01'])->assertCreated();

        $this->console($key)->postJson('/api/licenses/activate', ['instance_id' => self::CONSOLE_B])
            ->assertStatus(409)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('status', 'in_use_elsewhere')
            ->assertJsonPath('message', 'This licence is already in use by another Management Console (ops-01). Revoke it and create a new licence to move it.');

        $this->assertSame(self::CONSOLE_A, LicenseActivation::first()->instance_id);
    }

    public function test_heartbeat_records_last_seen(): void
    {
        $key = $this->newKey();
        $this->console($key)->postJson('/api/licenses/activate', ['instance_id' => self::CONSOLE_A])->assertCreated();

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

        $this->console($key)->postJson('/api/licenses/activate', ['instance_id' => self::CONSOLE_A])->assertCreated();

        $this->console($key)->postJson('/api/licenses/heartbeat', ['instance_id' => self::CONSOLE_B])
            ->assertStatus(409)
            ->assertJsonPath('status', 'in_use_elsewhere');
    }

    public function test_revoked_licence_stops_working_and_frees_activation(): void
    {
        $user = User::factory()->create();
        $key = $this->newKey($user);
        $this->console($key)->postJson('/api/licenses/activate', ['instance_id' => self::CONSOLE_A])->assertCreated();

        $user->tokens()->delete();

        $this->assertSame(0, LicenseActivation::count(), 'activation row is removed with the licence');
        $this->console($key)->postJson('/api/licenses/heartbeat', ['instance_id' => self::CONSOLE_A])->assertUnauthorized();
    }

    public function test_validation_and_auth(): void
    {
        $key = $this->newKey();

        $this->console($key)->postJson('/api/licenses/activate', ['instance_id' => 'short'])->assertUnprocessable()->assertJsonValidationErrors('instance_id');
        $this->console($key)->postJson('/api/licenses/activate', ['instance_id' => 'bad id with spaces'])->assertUnprocessable();

        $this->console('atg_bad')->postJson('/api/licenses/activate', ['instance_id' => self::CONSOLE_A])->assertUnauthorized();
        $this->console(null)->postJson('/api/licenses/heartbeat', ['instance_id' => self::CONSOLE_A])->assertUnauthorized();

        $other = User::factory()->create()->createToken('x', ['something:else'])->plainTextToken;
        $this->console($other)->postJson('/api/licenses/activate', ['instance_id' => self::CONSOLE_A])
            ->assertUnauthorized()
            ->assertJsonPath('valid', false);
    }

    public function test_browser_session_cannot_activate(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/licenses/activate', ['instance_id' => self::CONSOLE_A])
            ->assertUnauthorized();
    }

    public function test_dashboard_shows_in_use_and_last_seen(): void
    {
        $user = User::factory()->create();
        $key = $this->newKey($user);
        $this->console($key)->postJson('/api/licenses/activate', ['instance_id' => self::CONSOLE_A, 'hostname' => 'ops-01'])->assertCreated();

        $this->flushHeaders();
        $this->withHeaders(['Origin' => 'http://localhost:3000', 'Referer' => 'http://localhost:3000/', 'Accept' => 'application/json']);

        $this->actingAs($user)->getJson('/api/licenses')
            ->assertOk()
            ->assertJsonPath('licenses.0.in_use', true)
            ->assertJsonPath('licenses.0.console.hostname', 'ops-01')
            ->assertJsonStructure(['licenses' => [['console' => ['instance_id', 'hostname', 'version', 'activated_at', 'last_seen_at']]]]);
    }
}
