<?php

namespace Tests\Feature;

use App\Livewire\Licences;
use App\Livewire\Profile;
use App\Models\LicenseFile;
use App\Models\LicensePolicy;
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

    public function test_licence_page_emails_the_code_for_a_licence_file(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Licences::class)
            ->assertSee('Generate licence file')
            ->assertSee('Email verification code')
            ->call('sendCode')
            ->assertSet('codeMessage', "We sent a 5-digit code to {$user->email}. It expires in 10 minutes.")
            ->assertDispatched('toast');

        Notification::assertSentTo($user, LicenseCodeNotification::class);
    }

    public function test_licence_file_limit_on_the_dashboard(): void
    {
        LicensePolicy::global()->update(['max_files' => 1]);
        $user = User::factory()->create();
        LicenseFile::create([
            'license_id' => 'LIC-00000000-0000-4000-8000-000000000001', 'user_id' => $user->id, 'org' => 'x', 'plan' => 'free',
            'issued_at' => now(), 'activate_before' => now()->addHours(3), 'expires_at' => now()->addYear(), 'key_id' => 'test-2026',
        ]);
        $this->actingAs($user);

        Livewire::test(Licences::class)
            ->assertSee('1 / 1 active files')
            ->assertSee('To increase the limit, contact the AtGlance Support team')
            ->assertDontSee('Generate licence file');
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
