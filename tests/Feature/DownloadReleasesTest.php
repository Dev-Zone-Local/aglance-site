<?php

namespace Tests\Feature;

use App\Filament\Pages\DownloadSettings;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ReleasePublishedNotification;
use App\Support\Downloads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class DownloadReleasesTest extends TestCase
{
    use RefreshDatabase;

    private function sha(string $seed): string
    {
        return hash('sha256', $seed);
    }

    private function page()
    {
        return Livewire::actingAs(User::factory()->admin()->create())->test(DownloadSettings::class);
    }

    public function test_legacy_flat_settings_are_read(): void
    {
        Setting::put(Setting::DOWNLOADS, [
            'cli_url' => 'https://x/cli', 'cli_version' => '1.0.0', 'cli_checksum' => $this->sha('a'),
            'console_url' => 'https://x/console', 'console_version' => '1.0.0', 'console_checksum' => '',
            'cli_install_command' => 'curl x | bash',
        ]);

        $all = Downloads::all();
        $this->assertSame('1.0.0', $all['cli']['releases'][0]['version']);
        $this->assertStringContainsString('curl x | bash', $all['cli']['install_steps']);
        $this->assertNull($all['console']['releases'][0]['checksum']);
    }

    public function test_publish_puts_new_release_first_and_keeps_five(): void
    {
        foreach (['1.0', '1.1', '1.2', '1.3', '1.4', '1.5'] as $v) {
            Downloads::publish('cli', $v, $this->sha($v));
        }

        $releases = Downloads::all()['cli']['releases'];
        $this->assertCount(5, $releases);
        $this->assertSame(['1.5', '1.4', '1.3', '1.2', '1.1'], array_column($releases, 'version'));

        // Re-publishing an existing version replaces it and moves it to the top.
        Downloads::publish('cli', '1.3', $this->sha('fixed'));
        $releases = Downloads::all()['cli']['releases'];
        $this->assertSame(['1.3', '1.5', '1.4', '1.2', '1.1'], array_column($releases, 'version'));
        $this->assertSame($this->sha('fixed'), $releases[0]['checksum']);

        // Console history is separate.
        $this->assertSame([], Downloads::all()['console']['releases']);
    }

    public function test_validate_command(): void
    {
        Downloads::save(['cli' => ['url' => 'https://x', 'file_name' => 'atglance_2.0.0_all.deb', 'install_steps' => null, 'releases' => []], 'console' => []]);
        $product = Downloads::publish('cli', '2.0.0', $this->sha('z'));

        $this->assertSame(
            'echo "'.$this->sha('z').'  atglance_2.0.0_all.deb" | sha256sum -c -',
            Downloads::validateCommand($product)
        );
    }

    public function test_admin_publishes_release_from_page_and_notifies_verified_subscribers(): void
    {
        Notification::fake();
        $subscriber = User::factory()->create();
        $optedOut = User::factory()->create(['notify_updates' => false]);
        $unverified = User::factory()->unverified()->create();

        $this->page()
            ->set('data.cli.platforms.linux.url', 'https://downloads.atglance.live/cli/atglance_2.1.0_all.deb')
            ->set('data.cli.platforms.linux.file_name', 'atglance_2.1.0_all.deb')
            ->set('data.cli.platforms.linux.install_steps', "```bash\nsudo dpkg -i atglance_2.1.0_all.deb\n```")
            ->set('data.cli.new_version', '2.1.0')
            ->set('data.cli.new_checksum', strtoupper($this->sha('cli-2.1.0')))
            ->set('data.cli.new_notes', 'Faster discovery.')
            ->set('data.cli.notify', true)
            ->set('data.console.url', 'https://downloads.atglance.live/console/')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('data.cli.new_version', null);

        $cli = Downloads::all()['cli'];
        $this->assertSame('2.1.0', $cli['releases'][0]['version']);
        $this->assertSame($this->sha('cli-2.1.0'), $cli['releases'][0]['checksum'], 'stored lowercase');
        $this->assertSame('atglance_2.1.0_all.deb', $cli['file_name']);

        Notification::assertSentTo($subscriber, ReleasePublishedNotification::class,
            fn ($n) => $n->version === '2.1.0' && $n->productName === 'AtGlance CLI' && $n->notes === 'Faster discovery.');
        Notification::assertNotSentTo([$optedOut, $unverified], ReleasePublishedNotification::class);
    }

    public function test_no_email_when_notify_is_off(): void
    {
        Notification::fake();
        User::factory()->create();

        $this->page()
            ->set('data.cli.platforms.linux.url', 'https://x/cli')
            ->set('data.console.url', 'https://x/console')
            ->set('data.console.new_version', '3.0.0')
            ->set('data.console.new_checksum', $this->sha('c'))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('3.0.0', Downloads::all()['console']['releases'][0]['version']);
        Notification::assertNothingSent();
    }

    public function test_checksum_is_validated(): void
    {
        $this->page()
            ->set('data.cli.platforms.linux.url', 'https://x/cli')
            ->set('data.console.url', 'https://x/console')
            ->set('data.cli.new_version', '2.2.0')
            ->set('data.cli.new_checksum', 'not-a-sha')
            ->call('save')
            ->assertHasErrors(['data.cli.new_checksum']);

        $this->page()
            ->set('data.cli.platforms.linux.url', 'https://x/cli')
            ->set('data.console.url', 'https://x/console')
            ->set('data.cli.new_version', '2.2.0')
            ->call('save')
            ->assertHasErrors(['data.cli.new_checksum']);
    }

    public function test_dashboard_payload(): void
    {
        Downloads::save(['cli' => ['url' => 'https://x/cli', 'file_name' => 'cli.deb', 'install_steps' => 'steps', 'releases' => []], 'console' => []]);
        Downloads::publish('cli', '1.0', $this->sha('1'));
        Downloads::publish('cli', '2.0', $this->sha('2'));

        $this->actingAs(User::factory()->create())->getJson('/api/downloads')
            ->assertOk()
            ->assertJsonPath('cli.latest.version', '2.0')
            ->assertJsonPath('cli.latest.validate_command', 'echo "'.$this->sha('2').'  cli.deb" | sha256sum -c -')
            ->assertJsonPath('cli.previous.0.version', '1.0')
            ->assertJsonPath('cli.install_steps', 'steps')
            ->assertJsonPath('cli_version', '2.0')
            ->assertJsonPath('console.latest', null);
    }

    public function test_console_has_linux_and_windows_instructions(): void
    {
        // Old single URL / install steps become the Linux values.
        Downloads::save(['cli' => [], 'console' => ['url' => 'https://x/install.sh', 'file_name' => 'install.sh', 'install_steps' => 'old steps', 'releases' => []]]);
        $console = Downloads::all()['console'];
        $this->assertSame('old steps', $console['platforms']['linux']['install_steps']);
        $this->assertSame('https://x/install.sh', $console['platforms']['linux']['url']);
        $this->assertNull($console['platforms']['windows']['url']);
        // The CLI has Linux only; the console has Linux + Windows.
        $this->assertSame(['linux'], array_keys(Downloads::all()['cli']['platforms']));
        $this->assertSame(['linux', 'windows'], array_keys($console['platforms']));

        $this->page()
            ->set('data.cli.platforms.linux.url', 'https://x/cli')
            ->set('data.console.platforms.linux.url', 'https://x/linux/install.sh')
            ->set('data.console.platforms.linux.file_name', 'install.sh')
            ->set('data.console.platforms.linux.update_steps', '```bash
sudo ./update-console.sh
```')
            ->set('data.console.platforms.linux.script_url', 'https://x/update-console.sh')
            ->set('data.console.platforms.windows.url', 'https://x/windows/install-console.ps1')
            ->set('data.console.platforms.windows.file_name', 'install-console.ps1')
            ->set('data.console.platforms.windows.install_steps', "```powershell
.\install-console.ps1
```")
            ->set('data.console.platforms.windows.update_steps', 'Run the update script as Administrator.')
            ->set('data.console.platforms.windows.script_url', 'https://x/update-console.ps1')
            ->set('data.console.new_version', '2.0.0') // no checksum needed for the console
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('2.0.0', Downloads::all()['console']['releases'][0]['version']);
        $this->assertNull(Downloads::all()['console']['releases'][0]['checksum']);

        $this->actingAs(User::factory()->create())->getJson('/api/downloads')
            ->assertJsonPath('console.has_checksum', false)
            ->assertJsonPath('console.platforms.linux.url', 'https://x/linux/install.sh')
            ->assertJsonPath('console.platforms.windows.url', 'https://x/windows/install-console.ps1')
            ->assertJsonPath('console.platforms.windows.file_name', 'install-console.ps1')
            ->assertJsonPath('console.platforms.linux.script_url', 'https://x/update-console.sh')
            ->assertJsonPath('console.platforms.windows.update_steps', 'Run the update script as Administrator.')
            ->assertJsonPath('console.latest.version', '2.0.0')
            ->assertJsonPath('console.latest.checksum', null)
            ->assertJsonPath('console.latest.validate_command', null)
            ->assertJsonPath('console_url', 'https://x/linux/install.sh')
            ->assertJsonPath('cli.has_checksum', true)
            ->assertJsonMissingPath('cli.platforms.windows');
    }

    public function test_cli_has_separate_linux_update_section(): void
    {
        $this->page()
            ->set('data.cli.platforms.linux.url', 'https://x/atglance_2.1.0_all.deb')
            ->set('data.cli.platforms.linux.file_name', 'atglance_2.1.0_all.deb')
            ->set('data.cli.platforms.linux.install_steps', 'sudo dpkg -i atglance_2.1.0_all.deb')
            ->set('data.cli.platforms.linux.update_steps', 'sudo ./update-cli.sh')
            ->set('data.cli.platforms.linux.script_url', 'https://x/update-cli.sh')
            ->set('data.console.platforms.linux.url', 'https://x/install.sh')
            ->call('save')
            ->assertHasNoErrors();

        $cli = Downloads::all()['cli'];
        $this->assertSame('https://x/update-cli.sh', $cli['platforms']['linux']['script_url']);
        $this->assertSame('sudo ./update-cli.sh', $cli['platforms']['linux']['update_steps']);
        // Product-level values mirror Linux (checksum command + legacy keys).
        $this->assertSame('atglance_2.1.0_all.deb', $cli['file_name']);
        $this->assertSame('https://x/atglance_2.1.0_all.deb', $cli['url']);
    }

    public function test_console_checksum_is_never_stored(): void
    {
        Downloads::publish('console', '1.0.0', $this->sha('x'));

        $this->assertNull(Downloads::all()['console']['releases'][0]['checksum']);
    }

    public function test_script_url_must_be_a_url(): void
    {
        $this->page()
            ->set('data.cli.platforms.linux.url', 'https://x/cli')
            ->set('data.console.url', 'https://x/console')
            ->set('data.console.platforms.windows.script_url', 'not a url')
            ->call('save')
            ->assertHasErrors(['data.console.platforms.windows.script_url']);
    }

    public function test_admin_edits_and_deletes_a_release_in_history(): void
    {
        Downloads::publish('cli', '1.0.0', $this->sha('1'));
        Downloads::publish('cli', '1.1.0', $this->sha('2'), 'first notes');

        // Edit the latest (index 0): new version, checksum and notes; position kept.
        $this->page()
            ->callAction('editRelease', data: ['version' => '1.1.1', 'checksum' => strtoupper($this->sha('fixed')), 'notes' => 'fixed notes'], arguments: ['product' => 'cli', 'index' => 0])
            ->assertHasNoActionErrors()
            ->assertNotified('Release updated');

        $releases = Downloads::all()['cli']['releases'];
        $this->assertSame(['1.1.1', '1.0.0'], array_column($releases, 'version'));
        $this->assertSame($this->sha('fixed'), $releases[0]['checksum']);
        $this->assertSame('fixed notes', $releases[0]['notes']);

        // Duplicate version is refused.
        $this->page()
            ->callAction('editRelease', data: ['version' => '1.0.0', 'checksum' => $this->sha('x'), 'notes' => null], arguments: ['product' => 'cli', 'index' => 0])
            ->assertNotified('Version 1.0.0 already exists.');

        // Bad checksum is refused.
        $this->page()
            ->callAction('editRelease', data: ['version' => '1.1.1', 'checksum' => 'nope'], arguments: ['product' => 'cli', 'index' => 0])
            ->assertHasActionErrors(['checksum']);

        // Delete the latest: the previous one becomes latest.
        $this->page()
            ->callAction('deleteRelease', arguments: ['product' => 'cli', 'index' => 0])
            ->assertNotified('Release deleted');
        $this->assertSame(['1.0.0'], array_column(Downloads::all()['cli']['releases'], 'version'));
    }

    public function test_console_release_edit_has_no_checksum(): void
    {
        Downloads::publish('console', '2.0.0');

        $this->page()
            ->callAction('editRelease', data: ['version' => '2.0.1', 'notes' => 'patch'], arguments: ['product' => 'console', 'index' => 0])
            ->assertHasNoActionErrors();

        $r = Downloads::all()['console']['releases'][0];
        $this->assertSame('2.0.1', $r['version']);
        $this->assertNull($r['checksum']);
    }

    public function test_unsubscribe_and_preferences(): void
    {
        $user = User::factory()->create();

        $this->get(URL::signedRoute('updates.unsubscribe', ['id' => $user->id]))
            ->assertRedirect('http://localhost:3000/dashboard?unsubscribed=1');
        $this->assertFalse($user->fresh()->notify_updates);

        $this->get(route('updates.unsubscribe', ['id' => $user->id]))
            ->assertRedirect('http://localhost:3000/dashboard?unsubscribed=invalid');

        $this->actingAs($user)->putJson('/api/account/preferences', ['notify_updates' => true])
            ->assertOk()
            ->assertJsonPath('notify_updates', true);
        $this->getJson('/api/auth/me')->assertJsonPath('notify_updates', true);
    }

    public function test_release_email_contains_unsubscribe_link(): void
    {
        $user = User::factory()->create();
        $mail = (new ReleasePublishedNotification('AtGlance CLI', '2.1.0', $this->sha('x'), null))->toMail($user);

        $this->assertSame('AtGlance CLI 2.1.0 is available', $mail->subject);
        $this->assertStringContainsString('updates/unsubscribe/'.$user->id, implode(' ', $mail->outroLines));
    }
}
