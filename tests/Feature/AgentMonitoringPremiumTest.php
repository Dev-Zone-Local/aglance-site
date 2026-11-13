<?php

namespace Tests\Feature;

use App\Filament\Pages\LicensePolicyPage;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\CustomLicensePolicy;
use App\Models\FeatureCatalog;
use App\Models\License;
use App\Models\LicensePolicy;
use App\Models\User;
use App\Support\LicensePolicy\PolicyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\LicenseFixtures;
use Tests\TestCase;

/**
 * Agent Monitoring (agent_monitoring, premium) on atglance.live: catalog and bundle text, an
 * individual grant on a custom licence, and the Premium Features bundle.
 */
class AgentMonitoringPremiumTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        LicenseFixtures::configure();
        $this->withoutVite();
    }

    private function generate(User $user): array
    {
        $policy = PolicyResolver::for($user);
        $expires = $policy['expires_on'] ?? now()->addMonthsNoOverflow($policy['validity_months']);
        $licence = License::findOrFail($user->createToken('console', [License::ABILITY], $expires)->accessToken->id);
        $licence->confirm();
        $res = $this->actingAs($user)->postJson("/api/licenses/{$licence->id}/file", ['install_id' => (string) Str::uuid()])->assertOk();

        return LicenseFixtures::decode($res->getContent())['payload'];
    }

    public function test_catalog_and_bundle_text(): void
    {
        $feature = FeatureCatalog::query()->where('key', 'agent_monitoring')->sole();
        $this->assertSame(['premium', 'Agent Monitoring'], [$feature->tier, $feature->name]);
        $this->assertArrayHasKey('agent_monitoring', FeatureCatalog::bundleMembers(FeatureCatalog::BUNDLE_PREMIUM));
        $this->assertSame('Includes NFS Storage, Azure Storage, Remote Tunnel, Agent Monitoring, Add servers from the console, and any premium features added later.', LicensePolicyPage::bundleDescription());
        $this->assertSame('Agent Monitoring only', FeatureCatalog::premiumSummary([], ['agent_monitoring']));
    }

    public function test_individual_grant_is_issued(): void
    {
        $user = User::factory()->create(['plan' => 'free']);
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->callAction('customLicense', data: [
                'max_licences' => 2, 'max_nodes' => 10, 'max_users' => 5, 'validity_value' => 6, 'validity_unit' => 'months',
                'activation_window_minutes' => 60, 'install_id_mode' => 'optional', 'bundles' => [],
                'premium_bundle' => false, 'individual_features' => ['agent_monitoring'],
            ])
            ->assertHasNoActionErrors();
        $this->assertSame(['agent_monitoring'], CustomLicensePolicy::sole()->individual_features);

        $p = $this->generate($user);
        $this->assertSame([], $p['bundles']);
        $this->assertSame(['agent_monitoring'], $p['features']);
        $this->assertSame('individual', PolicyResolver::featureSource($user, 'agent_monitoring'));
    }

    public function test_bundle_holders_get_it_through_the_bundle(): void
    {
        // Premium (Enterprise) plan: the Premium Features bundle, whatever the policy form says.
        $user = User::factory()->create(['plan' => 'enterprise']);

        $p = $this->generate($user);
        $this->assertSame(['premium'], $p['bundles']);
        $this->assertSame('bundle', PolicyResolver::featureSource($user, 'agent_monitoring'));
    }
}
