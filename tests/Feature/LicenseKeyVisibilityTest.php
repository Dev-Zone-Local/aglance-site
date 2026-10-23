<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LicenseKeyVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /** Request a licence through the dashboard; returns the create response (with key). */
    private function createLicense(User $user, string $name = 'prod'): array
    {
        Notification::fake();

        return $this->actingAs($user)->postJson('/api/licenses', ['name' => $name])->assertCreated()->json();
    }

    public function test_key_does_not_need_the_emailed_code(): void
    {
        $created = $this->createLicense(User::factory()->create());

        $this->getJson('/api/licenses')
            ->assertJsonPath('licenses.0.awaiting_code', true)
            ->assertJsonPath('licenses.0.key_visible_until', $created['key_visible_until']);
        $this->getJson("/api/licenses/{$created['id']}/key")->assertOk()->assertJsonPath('key', $created['key']);
    }

    public function test_key_can_be_viewed_again_within_30_minutes(): void
    {
        $user = User::factory()->create();
        $created = $this->createLicense($user);

        $this->assertNotNull($created['key_visible_until']);

        $this->travel(29)->minutes();

        $this->getJson('/api/licenses')->assertJsonPath('licenses.0.key_visible_until', $created['key_visible_until']);
        $this->getJson("/api/licenses/{$created['id']}/key")
            ->assertOk()
            ->assertJsonPath('key', $created['key']);
    }

    public function test_key_is_gone_after_30_minutes(): void
    {
        $user = User::factory()->create();
        $created = $this->createLicense($user);

        $this->travel(31)->minutes();

        $this->getJson("/api/licenses/{$created['id']}/key")->assertStatus(410);
        $this->getJson('/api/licenses')->assertJsonPath('licenses.0.key_visible_until', null);

        $this->assertNull(DB::table('personal_access_tokens')->where('id', $created['id'])->value('plain_key'));
    }

    public function test_key_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $created = $this->createLicense($user);

        $raw = DB::table('personal_access_tokens')->where('id', $created['id'])->value('plain_key');
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString($created['key'], $raw);
    }

    public function test_only_owner_can_view_key(): void
    {
        $owner = User::factory()->create();
        $created = $this->createLicense($owner);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/licenses/{$created['id']}/key")
            ->assertNotFound();
    }

    public function test_scheduled_command_forgets_expired_keys(): void
    {
        $user = User::factory()->create();
        $created = $this->createLicense($user);

        $this->travel(31)->minutes();
        $this->artisan('licenses:forget-expired-keys')->assertSuccessful();

        $this->assertNull(License::find($created['id'])->key_visible_until);
    }

    public function test_key_is_never_in_list_or_admin_json(): void
    {
        $user = User::factory()->create();
        $this->createLicense($user);

        $this->getJson('/api/licenses')->assertJsonMissingPath('licenses.0.key')->assertJsonMissingPath('licenses.0.plain_key');
        $this->assertArrayNotHasKey('plain_key', License::first()->toArray());
    }
}
