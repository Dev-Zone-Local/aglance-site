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
 * Azure Storage (azure_storage, premium) on atglance.live: catalog, Premium Features bundle,
 * custom licences with single premium features, the licence file issuer, audit log, the
 * dashboard, pricing and the help page.
 */
class AzureStoragePremiumTest extends TestCase
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

    /** A new confirmed licence of $user, and the payload of its licence file. */
    private function generate(User $user, array $extraInput = []): array
    {
        $policy = PolicyResolver::for($user);
        $expires = $policy['expires_on'] ?? now()->addMonthsNoOverflow($policy['validity_months']);
        $licence = License::findOrFail($user->createToken('console', [License::ABILITY], $expires)->accessToken->id);
        $licence->confirm();
        $res = $this->actingAs($user)->postJson("/api/licenses/{$licence->id}/file", ['install_id' => (string) Str::uuid()] + $extraInput)->assertOk();

        return LicenseFixtures::decode($res->getContent())['payload'];
    }

    public function test_catalog_lists_azure_storage_as_premium(): void
    {
        $azure = FeatureCatalog::query()->where('key', 'azure_storage')->sole();
        $this->assertSame('premium', $azure->tier);
        $this->assertSame('Azure Storage', $azure->name);
        $this->assertStringContainsString('Azure Blob Storage', (string) $azure->description);
        $this->assertSame('Azure Storage', FeatureCatalog::premiumOptions()['azure_storage']);
        $this->assertSame(['nfs_storage', 'azure_storage', 'remote_tunnel'], array_keys(FeatureCatalog::bundleMembers(FeatureCatalog::BUNDLE_PREMIUM)));
    }

    public function test_bundle_description_lists_both_premium_features(): void
    {
        $this->assertSame(
            'Includes NFS Storage, Azure Storage, Remote Tunnel, and any premium features added later.',
            LicensePolicyPage::bundleDescription(),
        );
    }

    public function test_premium_summary(): void
    {
        $this->assertSame('Azure Storage only', FeatureCatalog::premiumSummary([], ['azure_storage']));
        $this->assertSame('NFS Storage, Azure Storage', FeatureCatalog::premiumSummary([], ['azure_storage', 'nfs_storage']));
        $this->assertSame('All (bundle)', FeatureCatalog::premiumSummary(['premium'], []));
    }

    public function test_custom_licence_with_azure_storage_only(): void
    {
        $user = User::factory()->create(['plan' => 'free']);
        $this->actingAs($this->admin());

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->callAction('customLicense', data: $this->policy(['premium_bundle' => false, 'individual_features' => ['azure_storage']]))
            ->assertHasNoActionErrors();

        $custom = CustomLicensePolicy::sole();
        $this->assertSame([], $custom->bundles);
        $this->assertSame(['azure_storage'], $custom->individual_features);
        $this->assertDatabaseHas('policy_audit_log', ['subject_type' => 'custom_license_policy', 'field' => 'individual_features', 'new_value' => 'azure_storage']);

        // Client input never adds features: only the resolved policy does.
        $p = $this->generate($user, ['features' => ['nfs_storage'], 'bundles' => ['premium']]);
        $this->assertSame([], $p['bundles']);
        $this->assertSame(['azure_storage'], $p['features']);

        $this->actingAs($user)->get(route('dashboard.licences'))->assertOk()->assertSee('Azure Storage')->assertDontSee('data-testid="all-premium"', false);

        // Revoking the grant is audited too.
        $this->actingAs($this->admin());
        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->callAction('customLicense', data: $this->policy(['premium_bundle' => false, 'individual_features' => ['nfs_storage']]))
            ->assertHasNoActionErrors();
        $this->assertDatabaseHas('policy_audit_log', ['subject_type' => 'custom_license_policy', 'field' => 'individual_features', 'old_value' => 'azure_storage', 'new_value' => 'nfs_storage']);
    }

    public function test_bundle_holders_get_azure_through_the_bundle_not_features(): void
    {
        $user = User::factory()->create();
        LicensePolicy::global()->update(['bundles' => ['premium']]);

        $p = $this->generate($user);
        $this->assertSame(['premium'], $p['bundles']);
        $this->assertSame([], $p['features'], 'azure_storage is never written into features[] for bundle holders');
    }

    public function test_pricing_lists_premium_features_from_the_catalog(): void
    {
        $this->withHeader('Accept', 'text/html')->get(route('pricing'))
            ->assertOk()
            ->assertSee('data-testid="premium-features"', false)
            ->assertSee('data-testid="premium-feature-azure_storage"', false)
            ->assertSee('Azure Storage')
            ->assertSee('data-testid="premium-feature-nfs_storage"', false);
    }

    public function test_help_page_seed_covers_rbac_and_auth(): void
    {
        $docs = json_decode((string) file_get_contents(database_path('seeders/content/docs.json')), true);
        $page = collect($docs)->firstWhere('slug', 'azure-storage');
        $this->assertNotNull($page);
        $this->assertSame('Azure Storage', $page['title']);
        foreach (['Storage Blob Data Contributor', 'Managed identity', 'Service principal', 'SAS token', 'az ad sp create-for-rbac'] as $text) {
            $this->assertStringContainsString($text, $page['content']);
        }
    }
}
