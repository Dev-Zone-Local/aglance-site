<?php

namespace Tests\Feature;

use App\Filament\Pages\LicensePolicyPage;
use App\Models\CustomLicensePolicy;
use App\Models\FeatureCatalog;
use App\Models\LicensePolicy;
use App\Models\PricingPlan;
use App\Models\User;
use App\Support\LicensePolicy\FeatureCatalogSync;
use App\Support\LicensePolicy\LicenseCategory;
use App\Support\LicensePolicy\PolicyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Three licence categories: Free (free features), Premium (every premium and free feature, now
 * and later) and Custom (premium features chosen per user). Plan policies set limits only.
 */
class LicenseCategoryTest extends TestCase
{
    use RefreshDatabase;

    private string $contract;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contract = tempnam(sys_get_temp_dir(), 'features').'.json';
        copy(FeatureCatalogSync::path(), $this->contract);
        FeatureCatalogSync::$pathOverride = $this->contract;
        FeatureCatalogSync::resetCheck();
        // The plans of Admin → Pricing; each gets a licence policy.
        PricingPlan::create(['name' => 'Free', 'price' => '0', 'description' => '', 'features' => [], 'order' => 1]);
        PricingPlan::create(['name' => 'Enterprise', 'price' => 'Custom', 'description' => '', 'features' => [], 'order' => 2]);
    }

    protected function tearDown(): void
    {
        FeatureCatalogSync::$pathOverride = null;
        FeatureCatalogSync::resetCheck();
        @unlink($this->contract);
        parent::tearDown();
    }

    private function addPremiumFeature(string $key): void
    {
        $data = json_decode(file_get_contents($this->contract), true);
        $data['features'][] = ['key' => $key, 'name' => $key, 'description' => $key, 'tier' => 'premium', 'default' => false];
        file_put_contents($this->contract, json_encode($data));
        FeatureCatalogSync::resetCheck();
    }

    public function test_plans_map_to_categories(): void
    {
        $this->assertSame(LicenseCategory::FREE, LicenseCategory::forPlan('free'));
        $this->assertSame(LicenseCategory::PREMIUM, LicenseCategory::forPlan('enterprise'));
        $this->assertSame(LicenseCategory::FREE, LicenseCategory::forPlan('unknown-plan'));
    }

    public function test_free_plan_gets_no_premium_feature_even_if_its_policy_had_the_bundle(): void
    {
        // An old free-plan policy with the bundle ticked no longer grants premium features.
        LicensePolicy::global()->update(['bundles' => [FeatureCatalog::BUNDLE_PREMIUM]]);
        $user = User::factory()->create(['plan' => 'free']);

        $policy = PolicyResolver::for($user);
        $this->assertSame([], $policy['bundles']);
        $this->assertSame([], $policy['features']);
        $this->assertSame('Free', $policy['label']);
        $this->assertFalse(PolicyResolver::includes($user, 'nfs_storage'));
    }

    public function test_premium_plan_gets_every_premium_feature_now_and_later(): void
    {
        // Even when the enterprise policy was saved without the bundle.
        LicensePolicy::query()->updateOrCreate(['scope' => 'plan:enterprise'], ['inherit_global' => false, 'bundles' => [], 'individual_features' => [], 'max_nodes' => 500]);
        $user = User::factory()->create(['plan' => 'enterprise']);

        $policy = PolicyResolver::for($user);
        $this->assertSame([FeatureCatalog::BUNDLE_PREMIUM], $policy['bundles']);
        $this->assertSame('Premium', $policy['label']);
        $this->assertSame(500, $policy['max_nodes']); // limits still from the plan policy
        foreach (FeatureCatalog::premiumKeys() as $key) {
            $this->assertTrue(PolicyResolver::includes($user, $key), $key);
        }

        $this->addPremiumFeature('gpu_monitoring');
        $this->assertTrue(PolicyResolver::includes($user, 'gpu_monitoring'));
    }

    public function test_custom_licence_overrides_the_plan_category(): void
    {
        $enterprise = User::factory()->create(['plan' => 'enterprise']);
        CustomLicensePolicy::create(['user_id' => $enterprise->id, 'bundles' => [], 'individual_features' => ['nfs_storage'], 'max_licences' => 1]);

        $policy = PolicyResolver::for($enterprise);
        $this->assertSame('Custom', $policy['label']);
        $this->assertTrue(PolicyResolver::includes($enterprise, 'nfs_storage'));
        $this->assertFalse(PolicyResolver::includes($enterprise, 'remote_tunnel'));

        // A free user can get premium features only through a custom licence.
        $free = User::factory()->create(['plan' => 'free']);
        CustomLicensePolicy::create(['user_id' => $free->id, 'bundles' => [FeatureCatalog::BUNDLE_PREMIUM], 'individual_features' => [], 'max_licences' => 1]);
        $this->assertTrue(PolicyResolver::includes($free, 'remote_tunnel'));
    }

    public function test_admin_policy_page_shows_categories_and_saves_matching_features(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/license-policy')->assertOk()
            ->assertSee('Premium licence: every premium feature, now and later')
            ->assertSee('Free licence: every free feature, now and later');

        Livewire::actingAs($admin)->test(LicensePolicyPage::class)->call('save')->assertHasNoErrors();
        $enterprise = LicensePolicy::forPlan('enterprise');
        $free = LicensePolicy::forPlan('free');
        $this->assertSame([FeatureCatalog::BUNDLE_PREMIUM], $enterprise->bundles);
        $this->assertSame([], $free->bundles);
        $this->assertSame([], LicensePolicy::global()->fresh()->bundles);
    }
}
