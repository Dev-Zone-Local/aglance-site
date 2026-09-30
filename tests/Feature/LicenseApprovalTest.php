<?php

namespace Tests\Feature;

use App\Filament\Resources\Licenses\LicenseResource;
use App\Filament\Resources\Licenses\Pages\ListLicenses;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\RelationManagers\LicensesRelationManager;
use App\Filament\Support\LicenseActions;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\User;
use App\Notifications\LicenseApprovedNotification;
use App\Notifications\LicenseDeclinedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin approval is optional: only users flagged with license_requires_approval need it.
 */
class LicenseApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function licenseFor(User $user, string $name = 'prod', bool $verified = true): License
    {
        $license = License::findOrFail($user->createToken($name, [License::ABILITY])->accessToken->id);
        if ($verified) {
            $license->confirm();
        }

        return $license->fresh();
    }

    private function flaggedUser(array $attrs = []): User
    {
        return User::factory()->create(['license_requires_approval' => true, ...$attrs]);
    }

    private function manager(User $owner)
    {
        return Livewire::actingAs($this->admin)->test(LicensesRelationManager::class, [
            'ownerRecord' => $owner,
            'pageClass' => EditUser::class,
        ]);
    }

    public function test_statuses_without_approval(): void
    {
        $license = $this->licenseFor(User::factory()->create(), verified: false);
        $this->assertSame(License::UNVERIFIED, $license->status());
        $this->assertFalse($license->isUsable());

        $license->confirm();
        $this->assertSame(License::READY, $license->fresh()->status());
        $this->assertTrue($license->fresh()->isUsable());

        LicenseActivation::create([
            'token_id' => $license->id, 'instance_id' => 'console-1234', 'activated_at' => now(), 'last_seen_at' => now(),
        ]);
        $this->assertSame(License::IN_USE, $license->fresh()->status());
    }

    public function test_statuses_with_approval_required(): void
    {
        $license = $this->licenseFor($this->flaggedUser());
        $this->assertSame(License::UNDER_REVIEW, $license->status());
        $this->assertFalse($license->isUsable());

        $license->approve($this->admin);
        $this->assertSame(License::READY, $license->fresh()->status());
        $this->assertSame($this->admin->id, $license->fresh()->approved_by);
    }

    public function test_admin_can_flag_user_from_edit_form(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($this->admin)->test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertSchemaStateSet(['license_requires_approval' => false])
            ->fillForm(['license_requires_approval' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($user->fresh()->license_requires_approval);
    }

    public function test_approve_shown_only_while_licence_is_pending(): void
    {
        $ready = $this->licenseFor(User::factory()->create());
        $flagged = $this->licenseFor($this->flaggedUser());
        $awaitingCode = $this->licenseFor(User::factory()->create(), 'no-code', verified: false);

        Livewire::actingAs($this->admin)->test(ListLicenses::class)
            ->set('activeTab', 'all')
            ->assertTableActionHidden('approve', $ready)
            ->assertTableActionHidden('decline', $ready)
            ->assertTableActionVisible('approve', $flagged)
            ->assertTableActionVisible('decline', $flagged)
            ->assertTableActionVisible('approve', $awaitingCode)
            ->assertTableActionHidden('decline', $awaitingCode)
            ->assertTableColumnFormattedStateSet('status', 'Ready', $ready)
            ->assertTableColumnFormattedStateSet('status', 'Under review', $flagged)
            ->assertTableColumnFormattedStateSet('status', 'Awaiting code', $awaitingCode);
    }

    public function test_admin_approve_bypasses_missing_code(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'test@test.com']);
        $license = $this->licenseFor($user, 'dev2', verified: false);
        $this->assertFalse($license->isUsable());

        Livewire::actingAs($this->admin)->test(ListLicenses::class)
            ->set('activeTab', 'unverified')
            ->callTableAction('approve', $license)
            ->assertNotified('Licence "dev2" approved');

        $license = $license->fresh();
        $this->assertTrue($license->isConfirmed());
        $this->assertTrue($license->isUsable());
        $this->assertSame(License::READY, $license->status());
        $this->assertSame($this->admin->id, $license->approved_by, 'records which admin bypassed the code');
        Notification::assertSentTo($user, LicenseApprovedNotification::class);
    }

    public function test_bypassed_licence_activates_from_console(): void
    {
        $user = User::factory()->create();
        $new = $user->createToken('dev2', [License::ABILITY]);
        $key = Str::after($new->plainTextToken, '|');
        $console = fn () => $this->withHeaders(['Accept' => 'application/json', 'Authorization' => "Bearer {$key}"]);

        $this->flushHeaders();
        $console()->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => 'console-1234'])->assertForbidden();

        $this->actingAs($this->admin);
        LicenseActions::approveOne(License::find($new->accessToken->id));

        $this->flushHeaders();
        app('auth')->forgetGuards();
        $console()->postJson('/api/licenses/activate', ['org_name' => 'Acme Corp', 'instance_id' => 'console-1234'])->assertCreated();
    }

    public function test_admin_sees_licences_on_user_edit_page(): void
    {
        $user = $this->flaggedUser();
        $pending = $this->licenseFor($user, 'pending-one');

        $this->manager($user)
            ->assertCanSeeTableRecords([$pending])
            ->assertTableActionVisible('approve', $pending);

        $this->actingAs($this->admin)
            ->withHeaders(['Accept' => 'text/html'])
            ->get("/admin/users/{$user->id}/edit")
            ->assertOk()
            ->assertSee('Licences');

        $this->assertSame('Licences (1 awaiting approval)', LicensesRelationManager::getTitle($user, EditUser::class));
        $this->assertSame('Licences', LicensesRelationManager::getTitle(User::factory()->create(), EditUser::class));
    }

    public function test_admin_approves_licence_and_user_is_emailed(): void
    {
        Notification::fake();
        $user = $this->flaggedUser();
        $license = $this->licenseFor($user);

        $this->manager($user)
            ->callTableAction('approve', $license)
            ->assertNotified('Licence "prod" approved');

        $this->assertTrue($license->fresh()->isUsable());
        Notification::assertSentTo($user, LicenseApprovedNotification::class, fn ($n) => $n->licenseName === 'prod');
    }

    public function test_bulk_approve(): void
    {
        Notification::fake();
        $user = $this->flaggedUser(['plan' => 'enterprise']);
        $a = $this->licenseFor($user, 'a');
        $b = $this->licenseFor($user, 'b');

        $this->manager($user)->callTableBulkAction('approveSelected', [$a, $b]);

        $this->assertTrue($a->fresh()->isApproved());
        $this->assertTrue($b->fresh()->isApproved());
    }

    public function test_decline_removes_request_and_emails_reason(): void
    {
        Notification::fake();
        $user = $this->flaggedUser();
        $license = $this->licenseFor($user);

        Livewire::actingAs($this->admin)->test(ListLicenses::class)
            ->callTableAction('decline', $license, data: ['reason' => 'Please use your company email.'])
            ->assertNotified('Licence "prod" declined');

        $this->assertNull(License::find($license->id));
        Notification::assertSentTo($user, LicenseDeclinedNotification::class,
            fn ($n) => $n->licenseName === 'prod' && $n->reason === 'Please use your company email.');
    }

    public function test_admin_can_revoke_any_licence(): void
    {
        $user = User::factory()->create();
        $license = $this->licenseFor($user);

        $this->manager($user)->callTableAction('delete', $license);

        $this->assertNull(License::find($license->id));
    }

    public function test_central_page_tabs(): void
    {
        $alice = $this->flaggedUser(['email' => 'alice@example.com']);
        $bob = User::factory()->create(['email' => 'bob@example.com']);
        $awaiting = $this->licenseFor($alice, 'alice-console');
        $ready = $this->licenseFor($bob, 'bob-console');
        $unverified = $this->licenseFor(User::factory()->create(), 'new-one', verified: false);
        $otherToken = License::findOrFail($bob->createToken('not-a-licence', ['other'])->accessToken->id);

        $this->actingAs($this->admin)->withHeaders(['Accept' => 'text/html'])->get('/admin/licenses')->assertOk();

        Livewire::actingAs($this->admin)->test(ListLicenses::class)
            ->assertSet('activeTab', 'awaiting')
            ->assertCanSeeTableRecords([$awaiting])
            ->assertCanNotSeeTableRecords([$ready, $unverified, $otherToken])
            ->set('activeTab', 'unverified')
            ->assertCanSeeTableRecords([$unverified])
            ->assertCanNotSeeTableRecords([$ready, $awaiting])
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$awaiting, $ready, $unverified])
            ->assertCanNotSeeTableRecords([$otherToken])
            ->assertTableColumnStateSet('tokenable.email', 'alice@example.com', $awaiting)
            ->searchTable('bob')
            ->assertCanSeeTableRecords([$ready])
            ->assertCanNotSeeTableRecords([$awaiting]);
    }

    public function test_default_tab_is_all_when_nothing_awaits_approval(): void
    {
        $this->licenseFor(User::factory()->create());

        Livewire::actingAs($this->admin)->test(ListLicenses::class)->assertSet('activeTab', 'all');
    }

    public function test_non_admin_cannot_open_licences_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->withHeaders(['Accept' => 'text/html'])
            ->get('/admin/licenses')
            ->assertForbidden();
    }

    public function test_nav_badge_counts_only_licences_awaiting_approval(): void
    {
        $this->licenseFor($this->flaggedUser());
        $this->licenseFor($this->flaggedUser())->approve($this->admin);
        $this->licenseFor(User::factory()->create()); // no approval needed

        $this->assertSame('1', LicenseResource::getNavigationBadge());
    }
}
