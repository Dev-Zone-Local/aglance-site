<?php

namespace Tests\Feature;

use App\Filament\Pages\LicensePolicyPage;
use App\Filament\Resources\Licenses\Pages\ListLicenses;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Support\LicenseTable;
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
 * Remote Tunnel (remote_tunnel, premium) on atglance.live: catalog and bundle text, custom
 * licences, the issuer, the dashboard, the connector downloads (gated on the server), the admin
 * licences list, the feature page, help pages and the sitemap.
 */
class RemoteTunnelPremiumTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        LicenseFixtures::configure();
        $this->withoutVite();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** @return array<string, mixed> */
    private function policy(array $overrides = []): array
    {
        return array_merge([
            'max_licences' => 2, 'max_nodes' => 10, 'max_users' => 5, 'validity_value' => 6, 'validity_unit' => 'months',
            'activation_window_minutes' => 60, 'install_id_mode' => 'optional', 'bundles' => [], 'individual_features' => [],
        ], $overrides);
    }

    private function generate(User $user, array $extraInput = []): array
    {
        $policy = PolicyResolver::for($user);
        $expires = $policy['expires_on'] ?? now()->addMonthsNoOverflow($policy['validity_months']);
        $licence = License::findOrFail($user->createToken('console', [License::ABILITY], $expires)->accessToken->id);
        $licence->confirm();
        $res = $this->actingAs($user)->postJson("/api/licenses/{$licence->id}/file", ['install_id' => (string) Str::uuid()] + $extraInput)->assertOk();

        return LicenseFixtures::decode($res->getContent())['payload'];
    }

    private function withTunnel(User $user): void
    {
        $this->actingAs($this->admin());
        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->callAction('customLicense', data: $this->policy(['premium_bundle' => false, 'individual_features' => ['remote_tunnel']]))
            ->assertHasNoActionErrors();
    }

    public function test_catalog_and_bundle_text(): void
    {
        $tunnel = FeatureCatalog::query()->where('key', 'remote_tunnel')->sole();
        $this->assertSame(['premium', 'Remote Tunnel'], [$tunnel->tier, $tunnel->name]);
        $this->assertArrayHasKey('remote_tunnel', FeatureCatalog::bundleMembers(FeatureCatalog::BUNDLE_PREMIUM));
        $this->assertSame('Includes NFS Storage, Azure Storage, Remote Tunnel, and any premium features added later.', LicensePolicyPage::bundleDescription());
        $this->assertSame('NFS Storage, Remote Tunnel', FeatureCatalog::premiumSummary([], ['remote_tunnel', 'nfs_storage']));
        $this->assertSame('Remote Tunnel only', FeatureCatalog::premiumSummary([], ['remote_tunnel']));
    }

    public function test_individual_grant_is_issued_and_client_input_is_ignored(): void
    {
        $user = User::factory()->create(['plan' => 'free']);
        $this->withTunnel($user);
        $this->assertSame(['remote_tunnel'], CustomLicensePolicy::sole()->individual_features);
        $this->assertDatabaseHas('policy_audit_log', ['subject_type' => 'custom_license_policy', 'field' => 'individual_features', 'new_value' => 'remote_tunnel']);

        $p = $this->generate($user, ['features' => ['nfs_storage', 'azure_storage'], 'bundles' => ['premium']]);
        $this->assertSame([], $p['bundles']);
        $this->assertSame(['remote_tunnel'], $p['features']);
        $this->assertSame('individual', PolicyResolver::featureSource($user, 'remote_tunnel'));
    }

    public function test_bundle_holders_get_it_through_the_bundle(): void
    {
        $user = User::factory()->create();
        LicensePolicy::global()->update(['bundles' => ['premium']]);

        $p = $this->generate($user);
        $this->assertSame(['premium'], $p['bundles']);
        $this->assertSame([], $p['features']);
        $this->assertSame('bundle', PolicyResolver::featureSource($user, 'remote_tunnel'));
    }

    public function test_dashboard_shows_where_remote_tunnel_comes_from(): void
    {
        $user = User::factory()->create(['plan' => 'free']);
        $this->actingAs($user)->get(route('dashboard.licences'))->assertOk()->assertSee('data-testid="remote-tunnel-source"', false)->assertSee('Not included');

        $this->withTunnel($user);
        $this->actingAs($user)->get(route('dashboard.licences'))->assertOk()->assertSee('Included (granted individually)');

        $bundled = User::factory()->create();
        LicensePolicy::global()->update(['bundles' => ['premium']]);
        $this->actingAs($bundled)->get(route('dashboard.licences'))->assertOk()->assertSee('Included via Premium Features');
    }

    public function test_connector_downloads_are_gated_on_the_server(): void
    {
        $user = User::factory()->create(['plan' => 'free']);
        $this->actingAs($user)->get(route('dashboard.connector'))->assertOk()->assertSee('data-testid="connector-locked"', false)->assertDontSee('data-testid="connector-release"', false);
        $this->actingAs($user)->get(route('dashboard.connector.file', ['1.0.0', 'linux-amd64']))->assertForbidden();
        $this->get(route('dashboard.connector'))->assertOk(); // still signed in

        $this->withTunnel($user);
        $this->actingAs($user)->get(route('dashboard.connector'))->assertOk()->assertSee('data-testid="connector-release"', false)->assertSee('atglance-connector-linux-amd64');
        $this->actingAs($user)->get(route('dashboard.connector.file', ['1.0.0', 'linux-amd64']))
            ->assertRedirect(config('connector.base_url').'/1.0.0/atglance-connector-linux-amd64');
        $this->actingAs($user)->get(route('dashboard.connector.file', ['9.9.9', 'linux-amd64']))->assertNotFound();

        auth()->logout();
        $this->withHeader('Accept', 'text/html')->get(route('dashboard.connector.file', ['1.0.0', 'linux-amd64']))->assertRedirect(route('login'));
    }

    public function test_admin_licences_list_filters_by_premium_feature(): void
    {
        $with = User::factory()->create(['plan' => 'free', 'email' => 'with@example.test']);
        $without = User::factory()->create(['plan' => 'free', 'email' => 'without@example.test']);
        $this->withTunnel($with);
        $a = $with->createToken('console', [License::ABILITY], now()->addYear())->accessToken;
        $b = $without->createToken('console', [License::ABILITY], now()->addYear())->accessToken;

        $this->assertSame([$with->id], LicenseTable::ownersWith('remote_tunnel'));
        $this->actingAs($this->admin());
        Livewire::test(ListLicenses::class)
            ->filterTable('feature', 'remote_tunnel')
            ->assertCanSeeTableRecords([License::find($a->id)])
            ->assertCanNotSeeTableRecords([License::find($b->id)]);
        $this->assertSame('Remote Tunnel', LicenseTable::premiumLabel(License::find($a->id)));
    }

    public function test_feature_page_help_pages_and_sitemap(): void
    {
        $this->withHeader('Accept', 'text/html')->get(route('features.remote-tunnel'))->assertOk()
            ->assertSee('Agent Tunnel')->assertSee('Site-to-Site IPsec VPN')->assertSee('Direct')->assertSee('Mutual TLS', false);
        $this->get('/sitemap.xml')->assertOk()->assertSee('/features/remote-tunnel');
        $this->withHeader('Accept', 'text/html')->get(route('pricing'))->assertOk()->assertSee('data-testid="premium-feature-remote_tunnel"', false);

        $docs = collect(json_decode((string) file_get_contents(database_path('seeders/content/docs.json')), true));
        foreach (['remote-tunnel', 'agent-tunnel', 'ipsec-vpn', 'direct-connection', 'tunnel-authentication', 'tunnel-troubleshooting'] as $slug) {
            $page = $docs->firstWhere('slug', $slug);
            $this->assertNotNull($page, $slug);
            $this->assertSame('Remote Tunnel', $page['section']);
            // Links between the help pages point at pages that exist.
            preg_match_all('#\]\(/docs/([a-z0-9-]+)\)#', $page['content'], $m);
            foreach ($m[1] as $target) {
                $this->assertNotNull($docs->firstWhere('slug', $target), "$slug links to missing /docs/$target");
            }
        }
        $this->assertStringContainsString('| Agent tunnel | ❌ | Enrollment token (once) | ✅ mTLS, required |', $docs->firstWhere('slug', 'tunnel-authentication')['content']);
    }
}
