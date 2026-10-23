<?php

namespace Tests\Feature;

use App\Filament\Pages\LicensePolicyPage;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\CustomLicensePolicy;
use App\Models\FeatureCatalog;
use App\Models\LicenseFile;
use App\Models\LicensePolicy;
use App\Models\PolicyAuditLog;
use App\Models\PricingPlan;
use App\Models\User;
use App\Support\LicensePolicy\PolicyResolver;
use App\Support\LicensePolicy\PolicyRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'max_nodes' => 10, 'max_users' => 5, 'validity_value' => 6, 'validity_unit' => 'months',
            'activation_window_minutes' => 60, 'features' => ['sso', 'notifications'],
        ], $overrides);
    }

    private function generate(User $user): array
    {
        return LicenseFixtures::decode($this->actingAs($user)->postJson('/api/license/generate')->assertOk()->getContent())['payload'];
    }

    public function test_migration_seeds_the_catalog_and_the_default_policies(): void
    {
        $this->assertSame(array_column(LicenseFixtures::features(), 'key'), FeatureCatalog::keys());
        $this->assertNotNull(LicensePolicy::query()->where('scope', 'global')->first());
        foreach (array_keys(config('atglance.plans')) as $plan) {
            $this->assertTrue(LicensePolicy::forPlan($plan)->inherit_global);
        }
    }

    public function test_policy_tabs_follow_the_pricing_plans_and_global_holds_the_daily_cap(): void
    {
        PricingPlan::create(['name' => 'Pro Team', 'price' => '49', 'description' => '', 'features' => [], 'order' => 3]);
        $this->actingAs($this->admin());

        $this->assertSame(['global', 'plan:free', 'plan:enterprise', 'plan:pro_team'], LicensePolicyPage::policies()->pluck('scope')->all());

        Livewire::test(LicensePolicyPage::class)
            ->assertSee('Global default')
            ->assertSee('Pro Team plan')
            ->assertSee('Licence files per account per day')
            ->set('data.global.max_files_per_day', 0)
            ->call('save')
            ->assertHasErrors(['data.global.max_files_per_day'])
            ->set('data.global.max_files_per_day', 3)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(3, LicensePolicy::global()->fresh()->max_files_per_day);
        $this->assertDatabaseHas('policy_audit_log', ['field' => 'max_files_per_day', 'old_value' => '20', 'new_value' => '3']);

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
            ['features' => ['not_a_feature']],
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

        LicensePolicy::forPlan('enterprise')->update(['inherit_global' => false, 'max_nodes' => 50, 'features' => ['sso']]);
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
                'activation_window_minutes' => 45, 'features' => ['sso', 'workspaces'], 'notes' => 'Pilot customer',
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
        $this->assertSame(['sso', 'workspaces'], $p['features']);
        $this->assertSame(45 * 60, strtotime($p['activate_before']) - strtotime($p['issued_at']));
        $this->assertSame(['policy_type' => 'custom', 'max_nodes' => 3], LicenseFile::sole()->only(['policy_type', 'max_nodes']));

        $this->actingAs($user)->get(route('dashboard.licences'))->assertSee('Custom')->assertSee('Multiple Workspaces');
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
            ->assertSee('SSO Login');
    }
}
