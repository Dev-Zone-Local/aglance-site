<?php

namespace Tests\Feature;

use App\Livewire\Licences;
use App\Livewire\Profile;
use App\Models\License;
use App\Models\User;
use App\Notifications\LicenseCodeNotification;
use App\Support\Downloads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Signed-in pages: overview, install wizard, licences and profile (Livewire). */
class DashboardWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Plain browser requests (the base TestCase sends JSON headers for API tests).
        $this->flushHeaders();
        $this->withoutVite();
    }

    private function sentCode(User $user): string
    {
        $code = null;
        Notification::assertSentTo($user, LicenseCodeNotification::class, function ($n) use (&$code) {
            $code = $n->code;

            return true;
        });

        return $code;
    }

    public function test_overview_shows_versions_and_link_results(): void
    {
        Downloads::publish('cli', '2.3.0', str_repeat('a', 64));
        $user = User::factory()->create(['name' => 'Jane']);

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('Welcome back, Jane.')
            ->assertSee('v2.3.0');

        $this->get('/dashboard?verified=1')->assertSee('Email verified. Thanks!');
        $this->get('/dashboard?unsubscribed=1')->assertSee('You will no longer get emails about new releases.');
        $this->get('/dashboard?verified=bogus')->assertSee('That verification link is not valid.');
    }

    public function test_install_wizard_steps(): void
    {
        $all = Downloads::all();
        $all['console']['platforms']['windows']['update_steps'] = 'Run `update.ps1`';
        $all['console']['platforms']['windows']['script_url'] = 'https://x/update.ps1';
        Downloads::save($all);

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/dashboard/install')->assertOk()->assertSee('What do you want to install?');
        $this->get('/dashboard/install/console')->assertOk()->assertSee('Windows (Docker)')->assertSee('Coming soon');
        $this->get('/dashboard/install/console/windows')->assertOk()->assertSee('Install on Windows (Docker)')->assertSee('Activate with a licence');
        $this->get('/dashboard/install/console/windows?mode=update')->assertOk()
            ->assertSee('Update on Windows (Docker)')
            ->assertSee('https://x/update.ps1')
            ->assertSee('<code>update.ps1</code>', false);
        $this->get('/dashboard/install/nope')->assertRedirect('/dashboard/install');
        // "Coming soon" targets show the target list, not steps.
        $this->get('/dashboard/install/console/aws')->assertOk()->assertSee('Where do you want to run it?');
    }

    public function test_install_pages_link_to_quick_fixes(): void
    {
        $this->actingAs(User::factory()->create());

        // Product cards, the "where" page and the guided steps all link to the product's known problems.
        $this->get('/dashboard/install')->assertOk()
            ->assertSee('/known-problems?product=console', false)
            ->assertSee('/known-problems?product=cli', false)
            ->assertSee('Quick fixes');
        $this->get('/dashboard/install/console')->assertOk()
            ->assertSee('Where do you want to run it?')
            ->assertSee('/known-problems?product=console', false);
        $this->get('/dashboard/install/cli/linux')->assertOk()
            ->assertSee('/known-problems?product=cli', false)
            ->assertDontSee('How to resolve?');
        $this->get('/dashboard/install/console/linux')->assertOk()->assertDontSee('How to resolve?');
    }

    public function test_licence_create_confirm_and_show_key(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $component = Livewire::test(Licences::class)
            ->set('name', 'prod-console')
            ->call('create')
            ->assertHasNoErrors()
            ->assertSee('Copy the licence key for')
            ->assertSee('Activate licence');

        $licence = License::firstOrFail();
        $this->assertSame('unverified', $licence->status());
        $this->assertNotNull($component->get('shownKey')['key']);

        $component->set('code', '00000')->call('confirm')->assertHasErrors('code');

        $component->set('code', $this->sentCode($user))->call('confirm')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSet('pendingId', null);

        $this->assertSame('ready', $licence->fresh()->status());
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // Key can be shown again within 30 minutes.
        $component->call('dismissKey')->assertSet('shownKey', null)
            ->call('showKey', $licence->id)
            ->assertSet('shownKey.id', $licence->id);
    }

    public function test_licence_limit_on_free_plan(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Licences::class)->set('name', 'one')->call('create')->assertHasNoErrors();

        Livewire::test(Licences::class)
            ->assertSee('Revoke the existing one to create a new licence')
            ->set('name', 'two')->call('create')
            ->assertDispatched('toast', type: 'error');

        $this->assertSame(1, License::count());
    }

    public function test_revoke_with_password_or_code(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'secret123']);
        $this->actingAs($user);

        $component = Livewire::test(Licences::class)->set('name', 'a')->call('create');
        $licence = License::firstOrFail();

        $component->call('startRevoke', $licence->id)
            ->assertSee('Revoke licence')
            ->set('revokePassword', 'wrong')->call('revoke')
            ->assertHasErrors('revokePassword');
        $this->assertSame(1, License::count());

        $component->set('revokePassword', 'secret123')->call('revoke')->assertHasNoErrors();
        $this->assertSame(0, License::count());

        // Revoke with an emailed code.
        Notification::fake();
        $component->set('name', 'b')->call('create');
        $licence = License::firstOrFail();
        $this->sentCode($user); // the activation code
        Notification::fake();

        $component->call('startRevoke', $licence->id)->call('sendRevokeCode')
            ->assertSet('revokeMode', 'code')
            ->set('revokeCode', $this->sentCode($user))->call('revoke')
            ->assertHasNoErrors();
        $this->assertSame(0, License::count());
    }

    public function test_cannot_touch_other_users_licences(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $owner->createToken('theirs', [License::ABILITY]);
        $licence = License::firstOrFail();

        $this->actingAs(User::factory()->create());
        Livewire::test(Licences::class)
            ->call('startRevoke', $licence->id)
            ->assertSet('revokingId', null)
            ->assertDispatched('toast', type: 'error')
            ->call('showKey', $licence->id)
            ->assertSet('shownKey', null);

        $this->assertSame(1, License::count());
    }

    public function test_profile_name_password_and_notifications(): void
    {
        $user = User::factory()->create(['name' => 'Old', 'password' => 'secret123', 'notify_updates' => true]);
        $this->actingAs($user);

        $this->get('/dashboard/profile')->assertOk()->assertSee('Change password');

        Livewire::test(Profile::class)
            ->set('name', '  New Name ')->call('saveName')->assertHasNoErrors()
            ->set('current_password', 'wrong')->set('password', 'newpass1')->set('password_confirmation', 'newpass1')
            ->call('savePassword')->assertHasErrors('current_password')
            ->set('current_password', 'secret123')->call('savePassword')->assertHasNoErrors()
            ->assertSet('password', '')
            ->set('notifyUpdates', false);

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertTrue(Hash::check('newpass1', $user->password));
        $this->assertFalse((bool) $user->notify_updates);
    }

    public function test_github_only_account_can_set_first_password(): void
    {
        $user = User::factory()->create(['password' => null, 'auth_method' => 'github']);
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->assertSee('Set a password')
            ->set('password', 'first123')->set('password_confirmation', 'first123')
            ->call('savePassword')->assertHasNoErrors();

        $this->assertTrue(Hash::check('first123', $user->fresh()->password));
    }
}
