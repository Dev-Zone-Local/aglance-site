<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\LicenseCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LicenseTest extends TestCase
{
    use RefreshDatabase;

    private const ABILITY = 'console:license';

    /** Installer requests come from a server, not the SPA: no Origin/Referer, no session. */
    private function installer(?string $key)
    {
        $this->flushHeaders();
        app('auth')->forgetGuards();

        return $this->withHeaders(array_filter([
            'Accept' => 'application/json',
            'Authorization' => $key ? "Bearer {$key}" : null,
        ]));
    }

    /** Full dashboard flow: request the licence (returns the key), then verify with the emailed code. */
    private function createLicense(string $name)
    {
        Notification::fake();

        $res = $this->postJson('/api/licenses', ['name' => $name]);
        if ($res->status() === 201) {
            $this->postJson("/api/licenses/{$res->json('id')}/confirm", ['code' => $this->sentCode()])->assertOk();
        }

        return $res;
    }

    private function sentCode(): string
    {
        $code = null;
        Notification::assertSentTo(auth()->user(), LicenseCodeNotification::class, function (LicenseCodeNotification $n) use (&$code) {
            $code = $n->code;

            return true;
        });

        return $code;
    }

    public function test_request_is_saved_immediately_and_code_confirms_it(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $created = $this->postJson('/api/licenses', ['name' => 'prod'])
            ->assertCreated()
            ->assertJsonPath('status', 'unverified')
            ->assertJsonPath('status_label', 'Awaiting code')
            ->assertJsonPath('requires_approval', false)
            ->assertJsonPath('confirmed', false)
            ->assertJsonPath('awaiting_code', true)
            ->assertJsonPath('code_sent', true)
            ->assertJsonPath('key', fn ($key) => (bool) preg_match('/^atg_\w+$/', $key))
            ->json();

        // Saved right away, so admins see it before the user enters the code.
        $this->assertSame(1, $user->licenses()->count());

        // Key is viewable right away, before the code is entered.
        $this->getJson("/api/licenses/{$created['id']}/key")->assertOk()->assertJsonPath('key', $created['key']);

        $code = null;
        Notification::assertSentTo($user, LicenseCodeNotification::class, function ($n) use (&$code) {
            $code = $n->code;

            return strlen($n->code) === 5 && ctype_digit($n->code) && $n->licenseName === 'prod' && $n->purpose === 'confirm';
        });

        $this->postJson("/api/licenses/{$created['id']}/confirm", ['code' => $code])
            ->assertOk()
            ->assertJsonPath('confirmed', true)
            ->assertJsonPath('awaiting_code', false)
            ->assertJsonPath('status', 'ready') // usable without admin approval
            ->assertJsonMissingPath('key');

        $this->assertTrue($user->fresh()->hasVerifiedEmail(), 'a correct code verifies the email');

        // Single use.
        $this->postJson("/api/licenses/{$created['id']}/confirm", ['code' => $code])->assertUnprocessable();
    }

    public function test_confirm_code_locks_after_five_wrong_attempts_and_can_be_resent(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->actingAs($user);
        $id = $this->postJson('/api/licenses', ['name' => 'prod'])->assertCreated()->json('id');
        $code = $this->sentCode();
        $wrong = $code === '00000' ? '11111' : '00000';

        foreach (range(1, 4) as $i) {
            $this->postJson("/api/licenses/{$id}/confirm", ['code' => $wrong])
                ->assertJsonPath('errors.code.0', 'The code is incorrect.');
        }
        $this->postJson("/api/licenses/{$id}/confirm", ['code' => $wrong])
            ->assertJsonPath('errors.code.0', 'Too many wrong attempts. Request a new code.');
        $this->postJson("/api/licenses/{$id}/confirm", ['code' => $code])->assertUnprocessable();

        Notification::fake();
        $this->postJson("/api/licenses/{$id}/confirm-code")->assertOk();
        $this->postJson("/api/licenses/{$id}/confirm", ['code' => $this->sentCode()])->assertOk();
    }

    public function test_code_is_bound_to_its_licence(): void
    {
        Notification::fake();
        $user = User::factory()->create(['plan' => 'enterprise']);
        $this->actingAs($user);

        $a = $this->postJson('/api/licenses', ['name' => 'a'])->json('id');
        $b = $this->postJson('/api/licenses', ['name' => 'b'])->json('id');

        $codes = [];
        Notification::assertSentTo($user, LicenseCodeNotification::class, function ($n) use (&$codes) {
            $codes[$n->licenseName] = $n->code;

            return true;
        });

        $this->postJson("/api/licenses/{$b}/confirm", ['code' => $codes['a']])->assertUnprocessable();
        $this->postJson("/api/licenses/{$a}/confirm", ['code' => $codes['a']])->assertOk();
        $this->postJson("/api/licenses/{$b}/confirm", ['code' => $codes['b']])->assertOk();
    }

    public function test_request_respects_plan_limit_and_deliverable_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $user->createToken('existing', [self::ABILITY]);

        $this->actingAs($user)->postJson('/api/licenses', ['name' => 'more'])->assertForbidden();
        Notification::assertNothingSent();

        $this->actingAs(User::factory()->create(['email' => 'octo@users.noreply.github.com']))
            ->postJson('/api/licenses', ['name' => 'x'])
            ->assertUnprocessable();
    }

    public function test_request_is_kept_when_email_fails(): void
    {
        $user = User::factory()->create();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

        $this->actingAs($user)->postJson('/api/licenses', ['name' => 'prod'])
            ->assertCreated()
            ->assertJsonPath('code_sent', false);

        $this->assertSame(1, $user->licenses()->count());
    }

    public function test_guest_cannot_manage_licenses(): void
    {
        $this->getJson('/api/licenses')->assertUnauthorized();
        $this->postJson('/api/licenses', ['name' => 'x'])->assertUnauthorized();
    }

    public function test_new_users_are_on_free_plan(): void
    {
        $this->postJson('/api/auth/register', ['email' => 'new@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertCreated()
            ->assertJsonPath('plan', 'free');
    }

    public function test_create_list_and_revoke(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $created = $this->createLicense('prod-console')
            ->assertCreated()
            ->assertJsonPath('name', 'prod-console')
            ->json();

        $this->assertMatchesRegularExpression('/^atg_\w+$/', $created['key'], 'no "<id>|" prefix');
        $this->assertNull($user->tokens()->first()->expires_at);

        $this->getJson('/api/licenses')
            ->assertOk()
            ->assertJsonPath('plan', 'free')
            ->assertJsonPath('plan_label', 'Free')
            ->assertJsonPath('limit', 1)
            ->assertJsonCount(1, 'licenses')
            ->assertJsonMissingPath('licenses.0.key');

        $this->deleteJson("/api/licenses/{$created['id']}", ['password' => 'password'])->assertOk();
        $this->getJson('/api/licenses')->assertJsonCount(0, 'licenses');
    }

    public function test_free_plan_allows_only_one_license(): void
    {
        $this->actingAs(User::factory()->create());

        $first = $this->createLicense('first')->assertSuccessful()->json();

        $this->createLicense('second')
            ->assertForbidden()
            ->assertJsonPath('message', 'Your Free plan includes 1 licence. Revoke an existing licence or upgrade your plan.');

        // Revoking frees the slot again.
        $this->deleteJson("/api/licenses/{$first['id']}", ['password' => 'password'])->assertOk();
        $this->createLicense('replacement')->assertSuccessful();
    }

    public function test_enterprise_plan_is_unlimited(): void
    {
        $user = User::factory()->create(['plan' => 'enterprise']);
        $this->actingAs($user);

        foreach (range(1, 3) as $i) {
            $this->createLicense("console-{$i}")->assertSuccessful();
        }

        $this->getJson('/api/licenses')->assertJsonPath('limit', null)->assertJsonCount(3, 'licenses');
    }

    public function test_unknown_plan_falls_back_to_free_limit(): void
    {
        $this->actingAs(User::factory()->create(['plan' => 'legacy']));

        $this->createLicense('one')->assertSuccessful();
        $this->createLicense('two')->assertForbidden();
    }

    public function test_other_tokens_do_not_count_toward_limit(): void
    {
        $user = User::factory()->create();
        $user->createToken('something-else', ['other:ability']);

        $this->actingAs($user);
        $this->createLicense('console')->assertSuccessful();
    }

    public function test_validation(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/licenses', [])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/licenses', ['name' => str_repeat('x', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_cannot_revoke_someone_elses_license(): void
    {
        $owner = User::factory()->create();
        $license = $owner->createToken('owner', [self::ABILITY])->accessToken;

        $this->actingAs(User::factory()->create())
            ->deleteJson("/api/licenses/{$license->id}", ['password' => 'password'])
            ->assertNotFound();

        $this->assertNotNull($license->fresh());
    }

    public function test_installer_verifies_valid_license(): void
    {
        $user = User::factory()->create(['email' => 'ops@example.com']);
        $new = $user->createToken('prod', [self::ABILITY]);
        $new->accessToken->forceFill(['confirmed_at' => now()])->save();
        $key = $new->plainTextToken;

        $this->installer($key)
            ->postJson('/api/licenses/verify')
            ->assertCreated()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('status', 'in_use')
            ->assertJsonPath('user.email', 'ops@example.com')
            ->assertJsonPath('license.name', 'prod')
            ->assertJsonPath('plan', 'free');

        $this->assertNotNull($user->tokens()->first()->last_used_at);
    }

    public function test_verify_rejects_bad_and_revoked_licenses(): void
    {
        $user = User::factory()->create();

        $this->installer('atg_nope')->postJson('/api/licenses/verify')->assertUnauthorized();
        $this->installer(null)->postJson('/api/licenses/verify')->assertUnauthorized();

        $revoked = $user->createToken('gone', [self::ABILITY]);
        $revoked->accessToken->delete();
        $this->installer($revoked->plainTextToken)->postJson('/api/licenses/verify')->assertUnauthorized();
    }

    public function test_verify_rejects_token_without_license_ability(): void
    {
        $key = User::factory()->create()->createToken('other', ['something:else'])->plainTextToken;

        $this->installer($key)
            ->postJson('/api/licenses/verify')
            ->assertUnauthorized()
            ->assertJsonPath('valid', false);
    }

    public function test_verify_rejects_browser_session(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/licenses/verify')
            ->assertUnauthorized()
            ->assertJsonPath('valid', false);
    }

    public function test_admin_can_change_plan_in_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->withHeaders(['Accept' => 'text/html'])
            ->get("/admin/users/{$user->id}/edit")
            ->assertOk()
            ->assertSee('Management Console licences');

        $this->withHeaders(['Accept' => 'text/html'])->get('/admin/users')->assertOk()->assertSee('Licences');
    }
}
