<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\LicenseCodeNotification;
use App\Support\LicenseCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LicenseRevokeTest extends TestCase
{
    use RefreshDatabase;

    private const ABILITY = 'console:license';

    private function sentRevokeCode(User $user): string
    {
        $code = null;
        Notification::assertSentTo($user, LicenseCodeNotification::class, function (LicenseCodeNotification $n) use (&$code) {
            $code = $n->code;

            return $n->purpose === LicenseCode::REVOKE;
        });

        return $code;
    }

    public function test_revoke_needs_password_or_code(): void
    {
        $user = User::factory()->create();
        $license = $user->createToken('prod', [self::ABILITY])->accessToken;

        $this->actingAs($user)->deleteJson("/api/licenses/{$license->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'Enter your password or the code we emailed you.');

        $this->assertNotNull($license->fresh());
    }

    public function test_revoke_with_password(): void
    {
        $user = User::factory()->create(['password' => 'secret-pw']);
        $license = $user->createToken('prod', [self::ABILITY])->accessToken;
        $this->actingAs($user);

        $this->deleteJson("/api/licenses/{$license->id}", ['password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'The password is incorrect.');
        $this->assertNotNull($license->fresh());

        $this->deleteJson("/api/licenses/{$license->id}", ['password' => 'secret-pw'])->assertOk();
        $this->assertNull($license->fresh());
    }

    public function test_revoke_with_emailed_code(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $license = $user->createToken('prod', [self::ABILITY])->accessToken;
        $this->actingAs($user);

        $this->postJson("/api/licenses/{$license->id}/revoke-code")->assertOk()->assertJsonPath('expires_in_minutes', 10);
        $code = $this->sentRevokeCode($user);

        $this->deleteJson("/api/licenses/{$license->id}", ['code' => $code === '00000' ? '11111' : '00000'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'The code is incorrect.');

        $this->deleteJson("/api/licenses/{$license->id}", ['code' => $code])->assertOk();
        $this->assertNull($license->fresh());
    }

    public function test_revoke_code_is_bound_to_one_licence(): void
    {
        Notification::fake();
        $user = User::factory()->create(['plan' => 'enterprise']);
        $a = $user->createToken('a', [self::ABILITY])->accessToken;
        $b = $user->createToken('b', [self::ABILITY])->accessToken;
        $this->actingAs($user);

        $this->postJson("/api/licenses/{$a->id}/revoke-code")->assertOk();
        $code = $this->sentRevokeCode($user);

        $this->deleteJson("/api/licenses/{$b->id}", ['code' => $code])->assertUnprocessable();
        $this->assertNotNull($b->fresh());
    }

    public function test_github_user_without_password_uses_code(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => null, 'auth_method' => 'github']);
        $license = $user->createToken('prod', [self::ABILITY])->accessToken;
        $this->actingAs($user);

        $this->getJson('/api/auth/me')->assertJsonPath('has_password', false);

        $this->deleteJson("/api/licenses/{$license->id}", ['password' => 'anything'])->assertUnprocessable();

        $this->postJson("/api/licenses/{$license->id}/revoke-code")->assertOk();
        $this->deleteJson("/api/licenses/{$license->id}", ['code' => $this->sentRevokeCode($user)])->assertOk();
    }

    public function test_revoke_and_create_codes_do_not_clash(): void
    {
        Notification::fake();
        $user = User::factory()->create(['plan' => 'enterprise']);
        $existing = $user->createToken('old', [self::ABILITY])->accessToken;
        $this->actingAs($user);

        $this->postJson('/api/licenses/code', ['name' => 'new'])->assertOk();
        $this->postJson("/api/licenses/{$existing->id}/revoke-code")->assertOk();

        $codes = [];
        Notification::assertSentTo($user, LicenseCodeNotification::class, function ($n) use (&$codes) {
            $codes[$n->purpose] = $n->code;

            return true;
        });

        $this->postJson('/api/licenses', ['name' => 'new', 'code' => $codes[LicenseCode::CREATE]])->assertCreated();
        $this->deleteJson("/api/licenses/{$existing->id}", ['code' => $codes[LicenseCode::REVOKE]])->assertOk();
    }

    public function test_cannot_request_revoke_code_for_someone_elses_licence(): void
    {
        Notification::fake();
        $other = User::factory()->create()->createToken('x', [self::ABILITY])->accessToken;

        $this->actingAs(User::factory()->create())
            ->postJson("/api/licenses/{$other->id}/revoke-code")
            ->assertNotFound();

        Notification::assertNothingSent();
    }
}
