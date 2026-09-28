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

    /** Full dashboard flow: request the emailed code, then create the licence with it. */
    private function createLicense(string $name)
    {
        Notification::fake();

        $res = $this->postJson('/api/licenses/code', ['name' => $name]);
        if ($res->status() !== 200) {
            return $res;
        }

        $code = null;
        Notification::assertSentTo(
            auth()->user(),
            LicenseCodeNotification::class,
            function (LicenseCodeNotification $n) use (&$code) {
                $code = $n->code;

                return true;
            }
        );

        return $this->postJson('/api/licenses', ['name' => $name, 'code' => $code]);
    }

    public function test_code_is_required_and_single_use(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $this->postJson('/api/licenses', ['name' => 'prod'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/licenses', ['name' => 'prod', 'code' => '12345'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'The code expired or was not requested. Request a new code.');

        $this->postJson('/api/licenses/code', ['name' => 'prod'])->assertOk()->assertJsonPath('expires_in_minutes', 10);

        $code = null;
        Notification::assertSentTo($user, LicenseCodeNotification::class, function ($n) use (&$code) {
            $code = $n->code;

            return strlen($n->code) === 5 && ctype_digit($n->code) && $n->licenseName === 'prod';
        });

        // Code is bound to the licence name.
        $this->postJson('/api/licenses', ['name' => 'other', 'code' => $code])->assertUnprocessable();

        $this->postJson('/api/licenses', ['name' => 'prod', 'code' => $code])->assertCreated();
        $this->assertTrue($user->fresh()->hasVerifiedEmail(), 'a correct code verifies the email');

        // Single use.
        $this->deleteJson('/api/licenses/'.$user->tokens()->first()->id, ['password' => 'password'])->assertOk();
        $this->postJson('/api/licenses', ['name' => 'prod', 'code' => $code])->assertUnprocessable();
    }

    public function test_code_locks_after_five_wrong_attempts(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/licenses/code', ['name' => 'prod'])->assertOk();

        $code = null;
        Notification::assertSentTo($user, LicenseCodeNotification::class, function ($n) use (&$code) {
            $code = $n->code;

            return true;
        });
        $wrong = $code === '00000' ? '11111' : '00000';

        foreach (range(1, 4) as $i) {
            $this->postJson('/api/licenses', ['name' => 'prod', 'code' => $wrong])
                ->assertJsonPath('errors.code.0', 'The code is incorrect.');
        }
        $this->postJson('/api/licenses', ['name' => 'prod', 'code' => $wrong])
            ->assertJsonPath('errors.code.0', 'Too many wrong attempts. Request a new code.');

        // Even the right code no longer works.
        $this->postJson('/api/licenses', ['name' => 'prod', 'code' => $code])->assertUnprocessable();
    }

    public function test_code_request_respects_plan_limit_and_deliverable_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $user->createToken('existing', [self::ABILITY]);

        $this->actingAs($user)->postJson('/api/licenses/code', ['name' => 'more'])->assertForbidden();
        Notification::assertNothingSent();

        $this->actingAs(User::factory()->create(['email' => 'octo@users.noreply.github.com']))
            ->postJson('/api/licenses/code', ['name' => 'x'])
            ->assertUnprocessable();
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

        $this->assertMatchesRegularExpression('/^\d+\|atg_\w+$/', $created['key']);
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

        $first = $this->createLicense('first')->assertCreated()->json();

        $this->createLicense('second')
            ->assertForbidden()
            ->assertJsonPath('message', 'Your Free plan includes 1 licence. Revoke an existing licence or upgrade your plan.');

        // Revoking frees the slot again.
        $this->deleteJson("/api/licenses/{$first['id']}", ['password' => 'password'])->assertOk();
        $this->createLicense('replacement')->assertCreated();
    }

    public function test_enterprise_plan_is_unlimited(): void
    {
        $user = User::factory()->create(['plan' => 'enterprise']);
        $this->actingAs($user);

        foreach (range(1, 3) as $i) {
            $this->createLicense("console-{$i}")->assertCreated();
        }

        $this->getJson('/api/licenses')->assertJsonPath('limit', null)->assertJsonCount(3, 'licenses');
    }

    public function test_unknown_plan_falls_back_to_free_limit(): void
    {
        $this->actingAs(User::factory()->create(['plan' => 'legacy']));

        $this->createLicense('one')->assertCreated();
        $this->createLicense('two')->assertForbidden();
    }

    public function test_other_tokens_do_not_count_toward_limit(): void
    {
        $user = User::factory()->create();
        $user->createToken('something-else', ['other:ability']);

        $this->actingAs($user);
        $this->createLicense('console')->assertCreated();
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
        $key = $user->createToken('prod', [self::ABILITY])->plainTextToken;

        $this->installer($key)
            ->postJson('/api/licenses/verify')
            ->assertOk()
            ->assertJsonPath('valid', true)
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
