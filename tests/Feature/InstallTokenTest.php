<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallTokenTest extends TestCase
{
    use RefreshDatabase;

    /** Installer requests come from a server, not the SPA: no Origin/Referer, no session. */
    private function installer(?string $token)
    {
        $this->flushHeaders();
        app('auth')->forgetGuards();

        return $this->withHeaders(array_filter([
            'Accept' => 'application/json',
            'Authorization' => $token ? "Bearer {$token}" : null,
        ]));
    }

    public function test_guest_cannot_manage_tokens(): void
    {
        $this->getJson('/api/install-tokens')->assertUnauthorized();
        $this->postJson('/api/install-tokens', ['name' => 'x'])->assertUnauthorized();
    }

    public function test_create_list_and_revoke(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $created = $this->postJson('/api/install-tokens', ['name' => 'prod-console'])
            ->assertCreated()
            ->assertJsonPath('name', 'prod-console')
            ->assertJsonMissingPath('expires_at')
            ->json();

        $this->assertMatchesRegularExpression('/^\d+\|atg_\w+$/', $created['token']);
        $this->assertNull($user->tokens()->first()->expires_at);

        $this->getJson('/api/install-tokens')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonMissingPath('0.token');

        $this->deleteJson("/api/install-tokens/{$created['id']}")->assertOk();
        $this->getJson('/api/install-tokens')->assertJsonCount(0);
    }

    public function test_validation(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/install-tokens', [])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/install-tokens', ['name' => str_repeat('x', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_active_token_limit(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 10) as $i) {
            $user->createToken("t{$i}", ['console:install']);
        }

        $this->actingAs($user)->postJson('/api/install-tokens', ['name' => 'one-too-many'])->assertStatus(422);
    }

    public function test_cannot_revoke_someone_elses_token(): void
    {
        $owner = User::factory()->create();
        $token = $owner->createToken('owner', ['console:install'])->accessToken;

        $this->actingAs(User::factory()->create())
            ->deleteJson("/api/install-tokens/{$token->id}")
            ->assertNotFound();

        $this->assertNotNull($token->fresh());
    }

    public function test_installer_verifies_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'ops@example.com']);
        $plain = $user->createToken('prod', ['console:install'])->plainTextToken;

        $this->installer($plain)
            ->postJson('/api/install-tokens/verify')
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('user.email', 'ops@example.com')
            ->assertJsonPath('token.name', 'prod');

        $this->assertNotNull($user->tokens()->first()->last_used_at);
    }

    public function test_verify_rejects_bad_and_revoked_tokens(): void
    {
        $user = User::factory()->create();

        $this->installer('atg_nope')->postJson('/api/install-tokens/verify')->assertUnauthorized();
        $this->installer(null)->postJson('/api/install-tokens/verify')->assertUnauthorized();

        $revoked = $user->createToken('gone', ['console:install']);
        $revoked->accessToken->delete();
        $this->installer($revoked->plainTextToken)->postJson('/api/install-tokens/verify')->assertUnauthorized();
    }

    public function test_verify_rejects_token_without_install_ability(): void
    {
        $plain = User::factory()->create()->createToken('other', ['something:else'])->plainTextToken;

        $this->installer($plain)
            ->postJson('/api/install-tokens/verify')
            ->assertUnauthorized()
            ->assertJsonPath('valid', false);
    }

    public function test_verify_rejects_browser_session(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/install-tokens/verify')
            ->assertUnauthorized()
            ->assertJsonPath('valid', false);
    }
}
