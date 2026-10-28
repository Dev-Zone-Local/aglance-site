<?php

namespace Tests\Feature;

use App\Filament\Pages\LicensePolicyPage;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\CustomLicensePolicy;
use App\Models\FeatureCatalog;
use App\Models\License;
use App\Models\LicenseFile;
use App\Models\LicensePolicy;
use App\Models\PolicyAuditLog;
use App\Models\PricingPlan;
use App\Models\User;
use App\Support\LicensePolicy\FeatureCatalogSync;
use App\Support\LicensePolicy\PolicyResolver;
use App\Support\LicensePolicy\PolicyRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\LicenseFixtures;
use Tests\TestCase;

/**
 * Admin → License Policy, Feature Catalog, Users → Create Custom License, and how the issuer
 * resolves the policy (custom → plan default → global default).
 */
class LicensePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        LicenseFixtures::configure();
        // License Policy shows one tab per plan of Admin → Pricing plans.
        PricingPlan::create(['name' => 'Free', 'price' => '0', 'description' => '', 'features' => [], 'order' => 1]);
        PricingPlan::create(['name' => 'Enterprise', 'price' => 'Custom', 'description' => '', 'features' => [], 'order' => 2]);
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
    private function generate(User $user): array
    {
        // Expiry from the policy, as LicenceManager::create() sets it.
        $policy = PolicyResolver::for($user);
        $expires = $policy['expires_on'] ?? now()->addMonthsNoOverflow($policy['validity_months']);
        $licence = License::findOrFail($user->createToken('console', [License::ABILITY], $expires)->accessToken->id);
        $licence->confirm();
        $res = $this->actingAs($user)->postJson("/api/licenses/{$licence->id}/file", ['install_id' => (string) Str::uuid()])->assertOk();

        return LicenseFixtures::decode($res->getContent())['payload'];
    }

    public function test_migration_seeds_the_catalog_and_the_default_policies(): void
    {
        $this->assertSame(array_column(LicenseFixtures::features(), 'key'), FeatureCatalog::keys());
        $this->assertNotNull(LicensePolicy::query()->where('scope', 'global')->first());
        foreach (array_keys(config('atglance.plans')) as $plan) {
            $this->assertTrue(LicensePolicy::forPlan($plan)->inherit_global);
        }
    }

    public function test_catalog_sync_removes_keys_no_longer_in_the_contract(): void
    {
        FeatureCatalog::create(['key' => 'retired_feature', 'name' => 'Retired', 'description' => '', 'default_enabled' => true, 'sort' => 99]);

        FeatureCatalogSync::sync();

        $this->assertSame(array_column(LicenseFixtures::features(), 'key'), FeatureCatalog::keys());
        $this->assertSame(['nfs_storage', 'azure_storage'], FeatureCatalog::premiumKeys(), 'a bundle is not a feature');
        $this->assertSame(['premium'], FeatureCatalog::bundleKeys());
        $this->assertSame(['nfs_storage' => 'NFS Storage', 'azure_storage' => 'Azure Storage'], FeatureCatalog::bundleMembers('premium'));
    }

    public function test_policy_tabs_follow_the_pricing_plans_and_set_the_licence_limit(): void
    {
        PricingPlan::create(['name' => 'Pro Team', 'price' => '49', 'description' => '', 'features' => [], 'order' => 3]);
        $this->actingAs($this->admin());

        $this->assertSame(['global', 'plan:free', 'plan:enterprise', 'plan:pro_team'], LicensePolicyPage::policies()->pluck('scope')->all());

        Livewire::test(LicensePolicyPage::class)
            ->assertSee('Global default')
            ->assertSee('Pro Team plan')
            ->assertSee('Licence limit')
            ->set('data.global.max_licences', 0)
            ->call('save')
            ->assertHasErrors(['data.global.max_licences'])
            ->set('data.global.max_licences', 3)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(3, LicensePolicy::global()->fresh()->max_licences);
        $this->assertDatabaseHas('policy_audit_log', ['field' => 'max_licences', 'old_value' => '1', 'new_value' => '3']);
        $this->assertSame(3, User::factory()->create(['plan' => 'free'])->licenseLimit());

        PricingPlan::query()->where('name', 'Pro Team')->delete();
        $this->assertNotContains('plan:pro_team', LicensePolicyPage::policies()->pluck('scope')->all());
        $this->assertNull(LicensePolicy::forPlan('pro_team'));
    }

    public function test_only_admins_can_open_the_policy_pages(): void
    {
        $user = User::factory()->create();
        foreach (['/admin/license-policy', '/admin/feature-catalog', '/admin/licence-files'] as $url) {
            $this->actingAs($user)->withHeader('Accept', 'text/html')->get($url)->assertForbidden();
        }

        $admin = $this->admin();
        foreach (['/admin/license-policy', '/admin/feature-catalog', '/admin/licence-files'] as $url) {
            $this->actingAs($admin)->withHeader('Accept', 'text/html')->get($url)->assertOk();
        }
    }

    public function test_validation_rejects_a_window_over_3_hours_negative_limits_and_long_validity(): void
    {
        foreach ([
            ['activation_window_minutes' => 181],
            ['activation_window_minutes' => 4],
            ['max_nodes' => -1],
            ['max_users' => -5],
            ['validity_value' => 11, 'validity_unit' => 'years'],
            ['install_id_mode' => 'sometimes'],
        ] as $bad) {
            try {
                PolicyRules::validate($this->policy($bad));
                $this->fail('Accepted '.json_encode($bad));
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(180, PolicyRules::validate($this->policy(['activation_window_minutes' => 180]))['activation_window_minutes']);
        $this->assertSame(0, PolicyRules::validate($this->policy(['max_nodes' => 0]))['max_nodes']);
    }

    public function test_policy_page_enforces_the_3_hour_cap_server_side(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(LicensePolicyPage::class)
            ->set('data.global.activation_window_minutes', 240)
            ->call('save')
            ->assertHasErrors(['data.global.activation_window_minutes']);

        $this->assertSame(180, LicensePolicy::global()->fresh()->activation_window_minutes);
    }

    public function test_saving_the_policy_is_audited_and_only_affects_new_licences(): void
    {
        $user = User::factory()->create(['plan' => 'free']);
        $before = $this->generate($user);
        $issued = LicenseFile::sole();

        $admin = $this->admin();
        $this->actingAs($admin);
        Livewire::test(LicensePolicyPage::class)
            ->set('data.global.max_nodes', 7)
            ->set('data.global.activation_window_minutes', 30)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(7, LicensePolicy::global()->fresh()->max_nodes);
        $this->assertDatabaseHas('policy_audit_log', [
            'admin_id' => $admin->id, 'subject_type' => 'license_policy', 'field' => 'max_nodes', 'old_value' => '0', 'new_value' => '7',
        ]);
        $this->assertDatabaseHas('policy_audit_log', ['field' => 'activation_window_minutes', 'old_value' => '180', 'new_value' => '30']);
        $this->assertSame(2, PolicyAuditLog::query()->where('subject_type', 'license_policy')->count());

        // The licence issued before keeps its values.
        $this->assertSame(0, $issued->fresh()->max_nodes);
        $this->assertSame(0, $before['max_nodes']);

        $after = $this->generate($user);
        $this->assertSame(7, $after['max_nodes']);
        $this->assertSame(1800, strtotime($after['activate_before']) - strtotime($after['issued_at']));

        $this->actingAs($admin);
        Livewire::test(LicensePolicyPage::class)->assertSee('Change history')->assertSee('max nodes')->assertSee($admin->email);
    }

    public function test_policy_resolution_order_custom_then_plan_then_global(): void
    {
        $user = User::factory()->create(['plan' => 'enterprise']);
        LicensePolicy::global()->update(['max_nodes' => 1]);
        $this->assertSame('global', PolicyResolver::for($user)['policy_id']);
        $this->assertSame(1, PolicyResolver::for($user)['max_nodes']);

        LicensePolicy::forPlan('enterprise')->update(['inherit_global' => false, 'max_nodes' => 50, 'features' => ['nfs_storage']]);
        $this->assertSame('plan:enterprise', PolicyResolver::for($user)['policy_id']);
        $this->assertSame(50, PolicyResolver::for($user)['max_nodes']);

        $custom = CustomLicensePolicy::create(['user_id' => $user->id] + $this->policy(['max_nodes' => 500]));
        $resolved = PolicyResolver::for($user);
        $this->assertSame('custom:'.$custom->id, $resolved['policy_id']);
        $this->assertSame('custom', $resolved['policy_type']);
        $this->assertSame(500, $resolved['max_nodes']);

        $custom->update(['revoked_at' => now()]);
        $this->assertSame('plan:enterprise', PolicyResolver::for($user)['policy_id']);
    }

    public function test_admin_creates_a_custom_licence_and_the_user_generates_it(): void
    {
        $user = User::factory()->create(['plan' => 'free']);
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->callAction('customLicense', data: $this->policy([
                'max_nodes' => 3, 'max_users' => 0, 'validity_value' => 2, 'validity_unit' => 'years',
                'activation_window_minutes' => 45, 'premium_bundle' => true, 'notes' => 'Pilot customer',
            ]))
            ->assertHasNoActionErrors();

        $custom = CustomLicensePolicy::sole();
        $this->assertSame($admin->id, $custom->created_by_admin_id);
        $this->assertDatabaseHas('policy_audit_log', ['subject_type' => 'custom_license_policy', 'action' => 'created', 'field' => 'max_nodes', 'new_value' => '3']);

        $p = $this->generate($user);
        $this->assertSame('custom', $p['policy_type']);
        $this->assertSame('custom:'.$custom->id, $p['policy_id']);
        $this->assertSame(3, $p['max_nodes']);
        $this->assertSame(0, $p['max_users']);
        $this->assertSame(24, $p['validity_months']);
        $this->assertSame(3, $p['format_version']);
        $this->assertSame(['premium'], $p['bundles']);
        $this->assertSame([], $p['features'], 'the bundle includes every premium feature: no separate grants');
        $this->assertSame(['premium'], $custom->bundles);
        $this->assertSame([], $custom->individual_features);
        $this->assertSame(45 * 60, strtotime($p['activate_before']) - strtotime($p['issued_at']));
        $this->assertSame(['policy_type' => 'custom', 'max_nodes' => 3], LicenseFile::sole()->only(['policy_type', 'max_nodes']));

        $this->assertSame(['premium'], LicenseFile::sole()->bundles);
        $this->actingAs($user)->get(route('dashboard.licences'))->assertSee('Custom')->assertSee('Premium Features (all)')->assertSee('Premium features in your licence');
    }

    public function test_custom_licence_rejects_a_window_over_3_hours(): void
    {
        $user = User::factory()->create();
        $this->actingAs($this->admin());

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->callAction('customLicense', data: $this->policy(['activation_window_minutes' => 200]))
            ->assertHasActionErrors(['activation_window_minutes']);

        $this->assertSame(0, CustomLicensePolicy::count());
    }

    public function test_revoking_falls_back_to_the_default_policy(): void
    {
        $user = User::factory()->create();
        $custom = CustomLicensePolicy::create(['user_id' => $user->id] + $this->policy());
        $this->actingAs($this->admin());

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])->callAction('revokeCustomLicense');

        $this->assertNotNull($custom->fresh()->revoked_at);
        $this->assertDatabaseHas('policy_audit_log', ['subject_type' => 'custom_license_policy', 'action' => 'revoked']);
        $this->assertSame('default', $this->generate($user)['policy_type']);
    }

    public function test_dashboard_shows_the_standard_policy_and_features(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard.licences'))
            ->assertOk()
            ->assertSee('Policy:')
            ->assertSee('Standard')
            ->assertSee('Node limit')
            ->assertSee('Premium features in your licence')
            ->assertSee('None')
            ->assertSee('Need more licences');
    }

    public function test_catalog_tiers_come_from_the_contract(): void
    {
        $this->assertSame('premium', FeatureCatalog::query()->where('key', 'nfs_storage')->value('tier'));
        $this->assertSame('free', FeatureCatalog::query()->where('key', 'sso')->value('tier'));
        $this->assertArrayHasKey('backup_restore', FeatureCatalog::freeOptions());
        $this->assertArrayNotHasKey('sso', FeatureCatalog::premiumOptions());

        // An admin edit of the tier does not survive a sync: the contract decides.
        FeatureCatalog::query()->where('key', 'sso')->update(['tier' => 'premium']);
        FeatureCatalogSync::sync();
        $this->assertSame('free', FeatureCatalog::query()->where('key', 'sso')->value('tier'));
    }

    public function test_free_features_and_bundle_keys_cannot_be_granted_one_by_one(): void
    {
        foreach ([['sso'], ['backup_restore', 'nfs_storage'], ['not_a_feature'], ['premium']] as $bad) {
            try {
                PolicyRules::validate($this->policy(['individual_features' => $bad]), true);
                $this->fail('Accepted '.json_encode($bad));
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
        foreach ([['enterprise_pack'], ['nfs_storage']] as $bad) {
            try {
                PolicyRules::validate($this->policy(['bundles' => $bad]), true);
                $this->fail('Accepted bundles '.json_encode($bad));
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(['nfs_storage'], PolicyRules::validate($this->policy(['individual_features' => ['nfs_storage', 'nfs_storage']]), true)['individual_features']);
    }

    public function test_default_policies_offer_only_the_premium_features_bundle(): void
    {
        // Server side: a default policy cannot grant single premium features.
        try {
            PolicyRules::validate($this->policy(['individual_features' => ['nfs_storage']]));
            $this->fail('A default policy accepted an individual grant');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('individual_features', $e->errors());
        }
        $this->assertSame(['premium'], PolicyRules::validate($this->policy(['bundles' => ['premium']]))['bundles']);

        $this->actingAs($this->admin());
        Livewire::test(LicensePolicyPage::class)
            ->assertSee('Premium Features')
            ->assertSee('Includes all premium features: NFS Storage, Azure Storage, and any premium features added later.')
            ->assertSee('Included with Premium Features')
            ->assertDontSee('Premium Features (all)')
            ->assertFormFieldExists('global.premium_bundle')
            ->assertFormFieldDoesNotExist('global.individual_features');
    }

    public function test_nfs_storage_through_the_bundle_by_default_and_one_by_one_in_custom_policies(): void
    {
        $user = User::factory()->create(['plan' => 'enterprise']);
        $this->assertSame(['bundles' => [], 'features' => []], array_intersect_key(PolicyResolver::for($user), array_flip(['bundles', 'features'])));

        LicensePolicy::global()->update(['bundles' => ['premium']]);
        $this->assertSame(['premium'], PolicyResolver::for($user)['bundles']);

        LicensePolicy::forPlan('enterprise')->update(['inherit_global' => false, 'bundles' => []]);
        $this->assertSame([], PolicyResolver::for($user)['bundles']);

        CustomLicensePolicy::create(['user_id' => $user->id] + $this->policy(['individual_features' => ['nfs_storage']]));
        $this->assertSame([], PolicyResolver::for($user)['bundles']);
        $this->assertSame(['nfs_storage'], PolicyResolver::for($user)['features']);
    }

    public function test_policy_page_saves_the_bundle_and_audits_it(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(LicensePolicyPage::class)
            ->set('data.global.premium_bundle', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['premium'], LicensePolicy::global()->fresh()->bundles);
        $this->assertSame([], LicensePolicy::global()->fresh()->individual_features);
        $this->assertDatabaseHas('policy_audit_log', ['subject_type' => 'license_policy', 'field' => 'bundles', 'new_value' => 'premium']);
        $this->assertDatabaseMissing('policy_audit_log', ['subject_type' => 'license_policy', 'field' => 'individual_features']);
    }

    public function test_custom_licence_with_single_premium_features(): void
    {
        $user = User::factory()->create(['plan' => 'free']);
        $this->actingAs($this->admin());

        // Unticked "Premium Features (all)": single features are stored as individual grants.
        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->callAction('customLicense', data: $this->policy(['premium_bundle' => false, 'individual_features' => ['nfs_storage']]))
            ->assertHasNoActionErrors();

        // The summary shown before saving.
        $this->assertSame('None', FeatureCatalog::premiumSummary([], []));
        $this->assertSame('NFS Storage only', FeatureCatalog::premiumSummary([], ['nfs_storage']));
        $this->assertSame('All (bundle)', FeatureCatalog::premiumSummary(['premium'], []));

        $custom = CustomLicensePolicy::sole();
        $this->assertSame([], $custom->bundles);
        $this->assertSame(['nfs_storage'], $custom->individual_features);
        $this->assertDatabaseHas('policy_audit_log', ['subject_type' => 'custom_license_policy', 'field' => 'individual_features', 'new_value' => 'nfs_storage']);

        $p = $this->generate($user);
        $this->assertSame([], $p['bundles']);
        $this->assertSame(['nfs_storage'], $p['features']);
        $this->actingAs($user)->get(route('dashboard.licences'))->assertSee('NFS Storage')->assertDontSee('data-testid="all-premium"', false);
    }

    public function test_every_premium_feature_picked_one_by_one_stays_individual_grants_with_a_hint(): void
    {
        $user = User::factory()->create(['plan' => 'free']);
        $this->actingAs($this->admin());

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->callAction('customLicense', data: $this->policy(['premium_bundle' => false, 'individual_features' => FeatureCatalog::premiumKeys()]))
            ->assertHasNoActionErrors();

        $this->assertStringContainsString('Tick "Premium Features (all)" to include them automatically.', LicensePolicyPage::individualHint(false, FeatureCatalog::premiumKeys()));
        $this->assertSame('Included through Premium Features (all).', LicensePolicyPage::individualHint(true, []));

        $custom = CustomLicensePolicy::sole();
        $this->assertSame([], $custom->bundles, 'not converted to the bundle');
        $this->assertSame(FeatureCatalog::premiumKeys(), $custom->individual_features);
    }

    public function test_bundle_and_individual_changes_are_audited_separately(): void
    {
        $user = User::factory()->create(['plan' => 'free']);
        $this->actingAs($this->admin());
        $action = fn (array $data) => Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->callAction('customLicense', data: $this->policy($data))->assertHasNoActionErrors();

        $action(['premium_bundle' => false, 'individual_features' => ['nfs_storage']]);
        $action(['premium_bundle' => true]);

        $updated = PolicyAuditLog::query()->where('subject_type', 'custom_license_policy')->where('action', 'updated')->get();
        $this->assertSame('premium', $updated->firstWhere('field', 'bundles')?->new_value);
        $this->assertSame('nfs_storage', $updated->firstWhere('field', 'individual_features')?->old_value);
        $this->assertEmpty($updated->firstWhere('field', 'individual_features')?->new_value);
    }

    public function test_issuer_writes_bundles_and_features_from_the_policy_only(): void
    {
        $user = User::factory()->create();
        // Stored data can contain stale, free or bundle keys: only known premium grants reach the licence.
        CustomLicensePolicy::create(['user_id' => $user->id] + $this->policy(['individual_features' => ['sso', 'nfs_storage', 'old_key', 'premium']]));

        $licence = License::findOrFail($user->createToken('prod', [License::ABILITY], now()->addYear())->accessToken->id);
        $licence->confirm();
        $res = $this->actingAs($user)->postJson("/api/licenses/{$licence->id}/file", [
            'install_id' => (string) Str::uuid(),
            'features' => ['premium', 'sso', 'quantum_reports'],
            'bundles' => ['premium'],
        ])->assertOk();

        $payload = LicenseFixtures::decode($res->getContent())['payload'];
        $this->assertSame(['nfs_storage'], $payload['features']);
        $this->assertSame([], $payload['bundles'], 'bundles never come from the request');
    }

    public function test_migration_converts_the_old_premium_key(): void
    {
        Artisan::call('migrate:rollback', ['--path' => 'database/migrations/2026_10_27_000100_premium_bundle.php', '--force' => true]);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $base = ['max_nodes' => 1, 'max_users' => 1, 'validity_value' => 1, 'validity_unit' => 'months', 'activation_window_minutes' => 60,
            'max_licences' => 1, 'install_id_mode' => 'optional', 'created_at' => now(), 'updated_at' => now()];
        DB::table('license_policies')->where('scope', 'global')->update(['features' => json_encode(['premium'])]);
        DB::table('license_policies')->insert($base + ['scope' => 'plan:old', 'inherit_global' => false, 'features' => json_encode(['nfs_storage'])]);
        DB::table('custom_license_policies')->insert($base + ['user_id' => $user->id, 'features' => json_encode(['premium', 'nfs_storage'])]);
        DB::table('custom_license_policies')->insert($base + ['user_id' => $other->id, 'features' => json_encode(['nfs_storage'])]);
        DB::table('licenses')->insert(['license_id' => 'LIC-'.Str::uuid(), 'user_id' => $user->id, 'org' => 'o', 'plan' => 'free', 'seats' => 0,
            'features' => json_encode(['premium', 'nfs_storage']), 'issued_at' => now(), 'activate_before' => now(), 'expires_at' => now()->addYear(),
            'key_id' => 'k', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('feature_catalog')->insert(['key' => 'premium', 'name' => 'Premium Features', 'description' => '', 'tier' => 'premium', 'default_enabled' => false, 'sort' => 50]);

        Artisan::call('migrate', ['--force' => true]);

        $this->assertSame(['premium'], LicensePolicy::global()->bundles);
        $this->assertSame(['premium'], LicensePolicy::query()->where('scope', 'plan:old')->value('bundles') ? LicensePolicy::query()->where('scope', 'plan:old')->first()->bundles : null,
            'a default policy with single grants gets the bundle (default policies offer only the bundle)');
        $this->assertSame([], LicensePolicy::query()->where('scope', 'plan:old')->first()->individual_features);
        $this->assertSame(['premium'], CustomLicensePolicy::query()->where('user_id', $user->id)->first()->bundles);
        $this->assertSame([], CustomLicensePolicy::query()->where('user_id', $user->id)->first()->individual_features);
        $this->assertSame([], CustomLicensePolicy::query()->where('user_id', $other->id)->first()->bundles);
        $this->assertSame(['nfs_storage'], CustomLicensePolicy::query()->where('user_id', $other->id)->first()->individual_features, 'custom single grants stay');
        $this->assertSame(['premium'], LicenseFile::sole()->bundles);
        $this->assertSame(['nfs_storage'], LicenseFile::sole()->features);
        $this->assertFalse(FeatureCatalog::query()->where('key', 'premium')->exists());
    }

    public function test_pricing_page_marks_nfs_storage_as_premium(): void
    {
        PricingPlan::query()->where('name', 'Enterprise')->update(['features' => ['Unlimited nodes', 'NFS Storage']]);

        $this->withHeader('Accept', 'text/html')->get(route('pricing'))
            ->assertOk()
            ->assertSee('NFS Storage')
            ->assertSee('data-testid="premium-badge"', false);
    }

    public function test_dashboard_shows_premium_features_all_for_the_bundle(): void
    {
        $user = User::factory()->create();
        LicensePolicy::global()->update(['bundles' => ['premium']]);

        $this->actingAs($user)->get(route('dashboard.licences'))
            ->assertOk()
            ->assertSee('data-testid="all-premium"', false)
            ->assertSee('Premium Features (all)');
    }
}
