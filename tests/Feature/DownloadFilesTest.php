<?php

namespace Tests\Feature;

use App\Filament\Pages\DownloadSettings;
use App\Models\DownloadFile;
use App\Models\User;
use App\Support\Downloads;
use App\Support\InstallCatalog;
use App\Support\ReleaseFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** Release files: upload, storage, every page that links them, and the old app.atglance.live host. */
class DownloadFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(ReleaseFiles::DISK);
        Storage::fake('local');
        config(['atglance.frontend_url' => 'https://atglance.test']);
    }

    private function tmp(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'agdl');
        file_put_contents($path, $content);

        return $path;
    }

    private function add(string $product, ?string $version, string $name, string $content = 'x', array $options = []): DownloadFile
    {
        return ReleaseFiles::store($product, $version, $this->tmp($content), $name, $options);
    }

    private function cliRelease(): void
    {
        Downloads::publish('cli', '2.2.0', null, null, ['title' => 'Service types']);
        $this->add('cli', '2.2.0', 'atglance_2.2.0_amd64.deb', 'deb-amd64');
        $this->add('cli', '2.2.0', 'atglance_2.2.0_arm64.deb', 'deb-arm64');
        $this->add('cli', '2.2.0', 'atglance-2.2.0-1.x86_64.rpm', 'rpm');
        $this->add('cli', '2.2.0', 'atglance_2.2.0_linux_amd64.tar.gz', 'tar');
        $this->add('cli', '2.2.0', 'atglance_2.2.0_windows_amd64.zip', 'zip');
    }

    public function test_detects_platform_arch_kind_and_version_from_the_name(): void
    {
        $this->assertSame(['platform' => 'linux', 'arch' => 'armhf', 'kind' => 'package', 'version' => '2.2.0'], ReleaseFiles::detect('atglance_2.2.0_armhf.deb'));
        $this->assertSame('aarch64', ReleaseFiles::detect('atglance-2.2.0-1.aarch64.rpm')['arch']);
        $this->assertSame('windows', ReleaseFiles::detect('atglance_3.0.0_windows_amd64.zip')['platform']);
        $this->assertSame('windows', ReleaseFiles::detect('atglance-3.0.0.msi')['platform']);
        $this->assertSame('macos', ReleaseFiles::detect('atglance_3.0.0_darwin_arm64')['platform']);
        $this->assertSame(['platform' => 'linux', 'arch' => null, 'kind' => 'installer', 'version' => null], ReleaseFiles::detect('atglance-installer.sh'));
        $this->assertSame('updater', ReleaseFiles::detect('atglance_update.ps1')['kind']);
        $this->assertSame('1.2', ReleaseFiles::detect('atglance_1.2.deb')['version']);
    }

    public function test_stores_file_with_checksum_and_serves_it(): void
    {
        $f = $this->add('cli', '2.2.0', 'atglance_2.2.0_amd64.deb', 'hello');

        $this->assertSame(hash('sha256', 'hello'), $f->sha256);
        $this->assertSame(5, $f->size);
        $this->assertSame('cli/atglance_2.2/atglance_2.2.0_amd64.deb', $f->legacy_path);
        Storage::disk(ReleaseFiles::DISK)->assertExists('cli/2.2.0/atglance_2.2.0_amd64.deb');

        $res = $this->get('/downloads/files/cli/2.2.0/atglance_2.2.0_amd64.deb')->assertOk();
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
        $this->assertSame(1, $f->fresh()->download_count);

        $this->get('/downloads/files/cli/2.2.0/nope.deb')->assertNotFound();
    }

    public function test_same_name_replaces_the_file(): void
    {
        $this->add('cli', '2.2.0', 'atglance_2.2.0_amd64.deb', 'one');
        $this->add('cli', '2.2.0', 'atglance_2.2.0_amd64.deb', 'two');

        $this->assertSame(1, DownloadFile::count());
        $this->assertSame(hash('sha256', 'two'), DownloadFile::first()->sha256);
    }

    public function test_refuses_files_a_server_could_run(): void
    {
        $this->assertFalse(ReleaseFiles::allowed('shell.php'));
        $this->assertFalse(ReleaseFiles::allowed('index.html'));
        $this->assertTrue(ReleaseFiles::allowed('atglance_2.2.0_linux_amd64.tar.gz'));

        $this->expectException(HttpException::class);
        $this->add('cli', '2.2.0', 'shell.php');
    }

    public function test_sha256sums_is_built_from_the_files(): void
    {
        $this->cliRelease();

        $body = $this->get('/downloads/files/cli/2.2.0/SHA256SUMS')->assertOk()->getContent();
        $this->assertStringContainsString(hash('sha256', 'deb-amd64').'  atglance_2.2.0_amd64.deb', $body);
        $this->assertSame(5, substr_count($body, "\n"));
    }

    public function test_latest_link_serves_the_newest_upload_of_a_name(): void
    {
        $this->add('console', '2.2.0', 'atglance-installer.sh', 'old');
        $this->add('console', '2.3.0', 'atglance-installer.sh', 'new');

        $res = $this->get('/downloads/latest/console/atglance-installer.sh')->assertOk();
        $this->assertSame('new', file_get_contents($res->getFile()->getPathname()));
        $this->assertStringContainsString('inline', $res->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('text/plain', $res->headers->get('Content-Type'));
    }

    public function test_resolved_downloads_use_the_uploaded_files(): void
    {
        $this->cliRelease();
        Downloads::publish('console', '2.3.0');
        $this->add('console', '2.3.0', 'atglance-installer.sh');
        $this->add('console', '2.3.0', 'atglance_update.sh');
        $this->add('console', '2.3.0', 'atglance-installer.ps1');

        $all = Downloads::resolved();

        $this->assertSame('atglance_2.2.0_amd64.deb', $all['cli']['platforms']['linux']['file_name']);
        $this->assertStringEndsWith('/downloads/files/cli/2.2.0/atglance_2.2.0_amd64.deb', $all['cli']['platforms']['linux']['url']);
        $this->assertSame('atglance_2.2.0_windows_amd64.zip', $all['cli']['platforms']['windows']['file_name']);
        $this->assertSame(hash('sha256', 'deb-amd64'), $all['cli']['releases'][0]['checksum']);
        $this->assertStringEndsWith('/downloads/latest/console/atglance-installer.sh', $all['console']['platforms']['linux']['url']);
        $this->assertStringEndsWith('/downloads/latest/console/atglance_update.sh', $all['console']['platforms']['linux']['script_url']);
        $this->assertStringEndsWith('/downloads/latest/console/atglance-installer.ps1', $all['console']['platforms']['windows']['url']);

        // The admin form still edits the saved values.
        $this->assertNull(Downloads::all()['cli']['platforms']['linux']['url']);

        // Dashboard installer: Windows CLI target with a PowerShell checksum check.
        $steps = collect(InstallCatalog::steps('cli', 'windows'));
        $this->assertStringContainsString('windows_amd64.zip', $steps->firstWhere('key', 'download')['action']['href']);
        $this->assertStringContainsString('Get-FileHash', $steps->firstWhere('key', 'verify')['code']);
        $this->assertSame(strtoupper(hash('sha256', 'zip')), str($steps->firstWhere('key', 'verify')['code'])->between('-eq "', '"')->toString());

        $this->actingAs(User::factory()->create())->getJson('/api/downloads')
            ->assertJsonPath('cli.latest.checksum', hash('sha256', 'deb-amd64'))
            ->assertJsonCount(5, 'cli.files')
            ->assertJsonPath('cli.files.0.file_name', 'atglance_2.2.0_amd64.deb');
    }

    public function test_public_pages_show_the_files(): void
    {
        $this->cliRelease();
        Downloads::publish('console', '2.3.0');
        $this->add('console', '2.3.0', 'atglance-installer.sh');

        $this->get('/downloads')->assertOk()
            ->assertSee('atglance_2.2.0_amd64.deb')
            ->assertSee('sudo apt-get install -y ./atglance_2.2.0_amd64.deb', false)
            ->assertSee('sudo rpm -Uvh atglance-2.2.0-1.x86_64.rpm', false)
            ->assertSee('Expand-Archive atglance_2.2.0_windows_amd64.zip', false)
            ->assertSee('/downloads/latest/console/atglance-installer.sh | sudo bash', false);

        $this->get('/cli')->assertOk()
            ->assertSee('atglance_2.2.0_amd64.deb')
            ->assertSee('Every command, one search away')
            ->assertSee('config restore')
            ->assertDontSee('--show-my-services');

        $this->get('/console')->assertOk()->assertSee('/downloads/latest/console/atglance-installer.sh', false)->assertSee('Which one do I use?');
        $this->get('/releases/cli/2.2.0')->assertOk()->assertSee('Files in this release')->assertSee('atglance-2.2.0-1.x86_64.rpm');
    }

    public function test_old_host_serves_files_at_their_old_paths(): void
    {
        $this->cliRelease();
        $this->add('console', null, 'atglance_update.sh', 'update', ['folder' => 'scripts', 'legacy_path' => 'console/atglance_update.sh']);
        $this->add('console', null, 'atglance-installer.sh', 'v1', ['folder' => 'old-v1', 'legacy_path' => 'console/Old-V1/atglance-installer.sh']);

        // The old host's routes are registered at boot from LEGACY_DOWNLOAD_HOST (default app.atglance.live).
        $legacy = config('atglance.legacy_download_host');
        $this->assertNotEmpty($legacy);
        $host = 'http://'.$legacy;
        $this->get("{$host}/cli/atglance_2.2/atglance_2.2.0_amd64.deb")->assertOk();
        $this->get("{$host}/console/Old-V1/atglance-installer.sh")->assertOk();
        // The old page linked a wrong name; it now serves the update script.
        $this->get("{$host}/console/atglance-updater.sh")->assertOk();
        $this->assertStringContainsString('atglance_2.2.0_arm64.deb', $this->get("{$host}/cli/atglance_2.2/SHA256SUMS")->assertOk()->getContent());

        $this->get("{$host}/")->assertRedirect('https://atglance.test/downloads')->assertStatus(301);
        $this->get("{$host}/cli/")->assertRedirect('https://atglance.test/downloads#cli');
        $this->get("{$host}/console/index.html")->assertRedirect('https://atglance.test/downloads#console');
        $this->get("{$host}/cli/atglance_9.9/missing.deb")->assertNotFound();
    }

    public function test_main_host_pages_are_not_taken_by_the_old_host_route(): void
    {
        $this->get('/cli')->assertOk()->assertSee('Command reference');
        $this->get('/console')->assertOk();
    }

    public function test_admin_uploads_files_when_publishing_and_removes_one(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(DownloadSettings::class)
            ->set('data.cli.new_version', '2.3.0')
            // One upload box per operating system.
            ->set('data.cli.new_files_linux', [UploadedFile::fake()->createWithContent('atglance_2.3.0_amd64.deb', 'amd')])
            ->set('data.cli.new_files_windows', [UploadedFile::fake()->createWithContent('atglance_2.3.0_windows_amd64.zip', 'win')])
            ->call('save')
            ->assertHasNoErrors();

        $release = Downloads::all()['cli']['releases'][0];
        $this->assertSame('2.3.0', $release['version']);
        // No checksum typed: the main Linux package's SHA-256 is used.
        $this->assertSame(hash('sha256', 'amd'), $release['checksum']);
        $files = ReleaseFiles::forRelease('cli', '2.3.0');
        $this->assertSame(['atglance_2.3.0_amd64.deb', 'atglance_2.3.0_windows_amd64.zip'], $files->pluck('file_name')->all());
        $this->assertSame('windows', $files[1]->platform);

        Livewire::actingAs($admin)->test(DownloadSettings::class)
            ->callAction('deleteFile', arguments: ['id' => $files[1]->id]);
        $this->assertSame(1, ReleaseFiles::forRelease('cli', '2.3.0')->count());
        Storage::disk(ReleaseFiles::DISK)->assertMissing('cli/2.3.0/atglance_2.3.0_windows_amd64.zip');
    }

    public function test_macos_packages_are_uploaded_listed_and_installable(): void
    {
        $this->assertTrue(ReleaseFiles::allowed('atglance_3.0.0_darwin_arm64'));
        $this->assertFalse(ReleaseFiles::allowed('atglance_3.0.0_linux_arm64'));
        $this->assertSame(['platform' => 'macos', 'arch' => 'arm64', 'kind' => 'package', 'version' => '3.0.0'], ReleaseFiles::detect('atglance_3.0.0_darwin_arm64'));

        Livewire::actingAs(User::factory()->admin()->create())->test(DownloadSettings::class)
            ->set('data.cli.new_version', '3.0.0')
            ->set('data.cli.new_files_linux', [UploadedFile::fake()->createWithContent('atglance_3.0.0_amd64.deb', 'deb')])
            ->set('data.cli.new_files_macos', [
                UploadedFile::fake()->createWithContent('atglance_3.0.0_darwin_arm64', 'mac-arm'),
                UploadedFile::fake()->createWithContent('atglance_3.0.0_darwin_amd64', 'mac-intel'),
            ])
            ->call('save')
            ->assertHasNoErrors();

        $mac = ReleaseFiles::forRelease('cli', '3.0.0')->where('platform', 'macos');
        $this->assertCount(2, $mac);
        $this->assertSame('macOS binary', $mac->first()->typeLabel());

        // Public install tab, admin overview and the dashboard installer.
        $this->get('/downloads')->assertOk()->assertSee('macOS')
            ->assertSee('sudo install -m 0755 atglance_3.0.0_darwin_arm64 /usr/local/bin/atglance', false);
        $this->actingAs(User::factory()->admin()->create())->get('/admin/download-settings')->assertOk()
            ->assertSee('macOS packages')->assertSee('2. Packages for Linux, Windows, macOS')->assertSee('atglance_3.0.0_darwin_amd64');
        $steps = collect(InstallCatalog::steps('cli', 'macos'));
        $this->assertStringContainsString('darwin_arm64', $steps->firstWhere('key', 'download')['action']['href']);
        $this->assertStringContainsString('shasum -a 256 -c', $steps->firstWhere('key', 'verify')['code']);
    }

    public function test_admin_adds_files_to_an_existing_release(): void
    {
        Downloads::publish('console', '2.3.0');

        Livewire::actingAs(User::factory()->admin()->create())->test(DownloadSettings::class)
            ->callAction('uploadFiles', data: [
                'files' => [UploadedFile::fake()->createWithContent('atglance-installer.ps1', 'ps')],
                'platform' => 'auto',
            ], arguments: ['product' => 'console', 'index' => 0])
            ->assertHasNoActionErrors();

        $f = DownloadFile::firstOrFail();
        $this->assertSame(['console', '2.3.0', 'windows', 'installer', 'console/atglance-installer.ps1'], [$f->product, $f->version, $f->platform, $f->kind, $f->legacy_path]);
    }

    public function test_import_legacy_keeps_old_paths(): void
    {
        Downloads::publish('console', '2.3.0');
        $dir = sys_get_temp_dir().'/agdl-legacy-'.uniqid();
        foreach ([
            'cli/atglance_2.2/atglance_2.2.0_amd64.deb', 'cli/atglance_2.2/atglance_2.1.0_arm64.deb', 'cli/atglance_1.2.deb',
            'cli/upgrade/upgrade_version.sh', 'console/atglance-installer.sh', 'console/Old-V1/atglance_update.ps1',
            'index.html', 'cli/index.html', '.user.ini',
        ] as $rel) {
            @mkdir(dirname("{$dir}/{$rel}"), 0777, true);
            file_put_contents("{$dir}/{$rel}", $rel);
        }

        $this->artisan('downloads:import-legacy', ['dir' => $dir])->assertSuccessful();

        $this->assertSame(6, DownloadFile::count());
        $byPath = DownloadFile::all()->keyBy('legacy_path');
        $this->assertSame('2.1.0', $byPath['cli/atglance_2.2/atglance_2.1.0_arm64.deb']->version);
        $this->assertSame('1.2', $byPath['cli/atglance_1.2.deb']->version);
        $this->assertNull($byPath['cli/upgrade/upgrade_version.sh']->version);
        $this->assertSame('2.3.0', $byPath['console/atglance-installer.sh']->version);
        $this->assertSame('old-v1', $byPath['console/Old-V1/atglance_update.ps1']->folder);

        // Running it again changes nothing.
        $this->artisan('downloads:import-legacy', ['dir' => $dir])->assertSuccessful();
        $this->assertSame(6, DownloadFile::count());
    }

    public function test_roadmap_feed_and_sitemap(): void
    {
        Downloads::publish('cli', '2.2.0', null, '### New
- Service types', ['summary' => 'Applications and system services shown apart.']);

        $this->get('/roadmap')->assertOk()->assertSee('Planned')->assertSee('In progress')->assertSee('Released')
            ->assertSee('Agent Monitoring')->assertSee(route('releases.show', ['cli', '2.2.0']), false);
        $this->get('/roadmap?product=cli')->assertOk()->assertDontSee('Google Drive backup storage');

        $feed = $this->get('/releases/rss.xml')->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8')->getContent();
        $xml = simplexml_load_string($feed);
        $this->assertSame('AtGlance CLI v2.2.0', (string) $xml->channel->item[0]->title);
        $this->assertSame('https://atglance.test/releases/cli/2.2.0', (string) $xml->channel->item[0]->link);

        $this->get('/sitemap.xml')->assertOk()->assertSee('https://atglance.test/downloads')->assertSee('https://atglance.test/roadmap');
        $this->get('/')->assertOk()->assertSee('rel="alternate" type="application/rss+xml"', false)->assertSee('CLI v2.2.0');
    }
}
