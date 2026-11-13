<?php

namespace Tests\Feature;

use App\Models\CustomLicensePolicy;
use App\Models\FeatureCatalog;
use App\Models\LicensePolicy;
use App\Models\User;
use App\Support\LicensePolicy\FeatureCatalogSync;
use App\Support\LicensePolicy\PolicyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * "Premium Features (all)" includes every premium feature, also ones added to the contract after
 * the licence or policy was set up, without a migration or an admin action.
 */
class PremiumBundleFutureFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private string $contract;

    protected function setUp(): void
    {
        parent::setUp();
        // A copy of the contract this test can add features to.
        $this->contract = tempnam(sys_get_temp_dir(), 'features').'.json';
        copy(FeatureCatalogSync::path(), $this->contract);
        FeatureCatalogSync::$pathOverride = $this->contract;
        FeatureCatalogSync::resetCheck();
    }

    protected function tearDown(): void
    {
        FeatureCatalogSync::$pathOverride = null;
        FeatureCatalogSync::resetCheck();
        @unlink($this->contract);
        parent::tearDown();
    }

    /** What happens when the console team ships a new premium feature: it is added to the contract. */
    private function addFeatureToContract(string $key, string $name): void
    {
        $data = json_decode(file_get_contents($this->contract), true);
        $data['features'][] = ['key' => $key, 'name' => $name, 'description' => $name, 'tier' => 'premium', 'default' => false, 'v1_default' => false, 'min_console_version' => '3.1.0'];
        file_put_contents($this->contract, json_encode($data));
        FeatureCatalogSync::resetCheck(); // a new request
    }

    private function userWithCustomPolicy(array $bundles, array $features): User
    {
        $user = User::factory()->create();
        CustomLicensePolicy::create(['user_id' => $user->id, 'bundles' => $bundles, 'individual_features' => $features, 'max_licences' => 1]);

        return $user;
    }

    public function test_bundle_includes_a_feature_added_later_without_a_migration(): void
    {
        $all = $this->userWithCustomPolicy([FeatureCatalog::BUNDLE_PREMIUM], []);
        $picked = $this->userWithCustomPolicy([], ['nfs_storage']);

        $this->assertFalse(PolicyResolver::includes($all, 'gpu_monitoring'));

        $this->addFeatureToContract('gpu_monitoring', 'GPU monitoring');

        // The bundle holder has it at once; a licence with single features does not.
        $this->assertTrue(PolicyResolver::includes($all, 'gpu_monitoring'));
        $this->assertSame('bundle', PolicyResolver::featureSource($all, 'gpu_monitoring'));
        $this->assertFalse(PolicyResolver::includes($picked, 'gpu_monitoring'));
        $this->assertArrayHasKey('gpu_monitoring', FeatureCatalog::bundleMembers(FeatureCatalog::BUNDLE_PREMIUM));
        $this->assertTrue(FeatureCatalog::query()->where('key', 'gpu_monitoring')->exists());
    }

    public function test_default_policy_with_the_bundle_includes_later_features(): void
    {
        // A premium (Enterprise) plan user, no custom licence.
        $user = User::factory()->create(['plan' => 'enterprise']);

        $this->addFeatureToContract('gpu_monitoring', 'GPU monitoring');

        $this->assertTrue(PolicyResolver::includes($user, 'gpu_monitoring'));
    }

    public function test_licence_file_carries_the_bundle_not_a_frozen_feature_list(): void
    {
        $all = $this->userWithCustomPolicy([FeatureCatalog::BUNDLE_PREMIUM], ['nfs_storage']);
        $policy = PolicyResolver::for($all);

        // The console resolves "premium" from its own catalog, so later features are included there too.
        $this->assertSame([FeatureCatalog::BUNDLE_PREMIUM], $policy['bundles']);
        $this->assertSame([], $policy['features']);
    }

    public function test_sync_runs_once_per_contract_change(): void
    {
        $this->addFeatureToContract('gpu_monitoring', 'GPU monitoring');
        FeatureCatalog::premiumKeys();
        $hash = Cache::get('feature_catalog.contract_hash');
        $this->assertSame(md5_file($this->contract), $hash);

        // An admin renames it; an unchanged contract does not overwrite that.
        FeatureCatalog::where('key', 'gpu_monitoring')->update(['name' => 'GPU (renamed)']);
        FeatureCatalogSync::resetCheck();
        $this->assertSame('GPU (renamed)', FeatureCatalog::premiumOptions()['gpu_monitoring']);
    }

    public function test_remote_deploy_is_part_of_the_bundle(): void
    {
        $this->assertArrayHasKey('remote_deploy', FeatureCatalog::bundleMembers(FeatureCatalog::BUNDLE_PREMIUM));
    }
}
