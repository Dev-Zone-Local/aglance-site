<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_guest_cannot_manage_licenses(): void
    {
        $this->getJson('/api/licenses')->assertUnauthorized();
        $this->postJson('/api/licenses', ['name' => 'x'])->assertUnauthorized();
    }

    public function test_new_users_are_on_free_plan(): void
    {
        $this->postJson('/api/auth/register', ['email' => 'new@example.com', 'password' => 'password123'])
            ->assertCreated()
            ->assertJsonPath('plan', 'free');
    }

    public function test_create_list_and_revoke(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $created = $this->postJson('/api/licenses', ['name' => 'prod-console'])
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

        $this->deleteJson("/api/licenses/{$created['id']}")->assertOk();
        $this->getJson('/api/licenses')->assertJsonCount(0, 'licenses');
    }

    public function test_free_plan_allows_only_one_license(): void
    {
        $this->actingAs(User::factory()->create());

        $first = $this->postJson('/api/licenses', ['name' => 'first'])->assertCreated()->json();

        $this->postJson('/api/licenses', ['name' => 'second'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Your Free plan includes 1 licence. Revoke an existing licence or upgrade your plan.');

        // Revoking frees the slot again.
        $this->deleteJson("/api/licenses/{$first['id']}")->assertOk();
        $this->postJson('/api/licenses', ['name' => 'replacement'])->assertCreated();
    }

    public function test_enterprise_plan_is_unlimited(): void
    {
        $user = User::factory()->create(['plan' => 'enterprise']);
        $this->actingAs($user);

        foreach (range(1, 3) as $i) {
            $this->postJson('/api/licenses', ['name' => "console-{$i}"])->assertCreated();
        }

        $this->getJson('/api/licenses')->assertJsonPath('limit', null)->assertJsonCount(3, 'licenses');
    }

    public function test_unknown_plan_falls_back_to_free_limit(): void
    {
        $this->actingAs(User::factory()->create(['plan' => 'legacy']));

        $this->postJson('/api/licenses', ['name' => 'one'])->assertCreated();
        $this->postJson('/api/licenses', ['name' => 'two'])->assertForbidden();
    }

    public function test_other_tokens_do_not_count_toward_limit(): void
    {
        $user = User::factory()->create();
        $user->createToken('something-else', ['other:ability']);

        $this->actingAs($user)->postJson('/api/licenses', ['name' => 'console'])->assertCreated();
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
            ->deleteJson("/api/licenses/{$license->id}")
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
