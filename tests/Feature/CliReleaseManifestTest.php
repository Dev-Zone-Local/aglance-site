<?php

namespace Tests\Feature;

use App\Models\DownloadFile;
use App\Support\CliReleaseManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** GET /api/releases/cli/latest: the newest CLI files, for the console's "add a server" flow. */
class CliReleaseManifestTest extends TestCase
{
    use RefreshDatabase;

    private function file(string $product, string $version, string $name, int $size = 10): DownloadFile
    {
        return DownloadFile::create([
            'product' => $product, 'folder' => $version, 'version' => $version, 'platform' => 'linux',
            'arch' => null, 'kind' => 'package', 'file_name' => $name, 'path' => "$product/$version/$name",
            'size' => $size, 'sha256' => hash('sha256', $name),
        ]);
    }

    public function test_file_names_are_classified(): void
    {
        $this->assertSame(['os' => 'linux', 'arch' => 'amd64', 'format' => 'deb'], CliReleaseManifest::classify('atglance_3.0.1_amd64.deb'));
        $this->assertSame(['os' => 'linux', 'arch' => 'arm64', 'format' => 'rpm'], CliReleaseManifest::classify('atglance-3.0.1-1.aarch64.rpm'));
        $this->assertSame(['os' => 'linux', 'arch' => 'amd64', 'format' => 'rpm'], CliReleaseManifest::classify('atglance-3.0.1-1.x86_64.rpm'));
        $this->assertSame(['os' => 'linux', 'arch' => 'armhf', 'format' => 'tar.gz'], CliReleaseManifest::classify('atglance_3.0.1_linux_armhf.tar.gz'));
        $this->assertSame(['os' => 'windows', 'arch' => 'arm64', 'format' => 'zip'], CliReleaseManifest::classify('atglance_3.0.1_windows_arm64.zip'));
        $this->assertSame(['os' => 'darwin', 'arch' => 'arm64', 'format' => 'binary'], CliReleaseManifest::classify('atglance_3.0.1_darwin_arm64'));
        $this->assertSame(['os' => 'linux', 'arch' => 'amd64', 'format' => 'deb'], CliReleaseManifest::classify('atglance_3.0.0-rc.1_amd64.deb'));
        $this->assertNull(CliReleaseManifest::classify('SHA256SUMS'));
        $this->assertNull(CliReleaseManifest::classify('atglance-installer.sh'));
    }

    public function test_latest_lists_only_the_newest_version(): void
    {
        // Inserted newest-first so id order differs from version order; 3.0.10 > 3.0.9 > 3.0.1.
        $this->file('cli', '3.0.1', 'atglance_3.0.1_amd64.deb', 111);
        $this->file('cli', '3.0.1', 'atglance_3.0.1_windows_amd64.zip', 222);
        $this->file('cli', '3.0.1', 'SHA256SUMS');
        $this->file('cli', '3.0.0', 'atglance_3.0.0_amd64.deb');
        $this->file('console', '9.9.9', 'atglance_9.9.9_amd64.deb');

        $res = $this->getJson('/api/releases/cli/latest')->assertOk();
        $res->assertJsonPath('version', '3.0.1');
        $this->assertStringContainsString('max-age=300', $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', $res->headers->get('Cache-Control'));

        $files = collect($res->json('files'))->keyBy('name');
        $this->assertSame(['atglance_3.0.1_amd64.deb', 'atglance_3.0.1_windows_amd64.zip'], $files->keys()->sort()->values()->all());
        $deb = $files['atglance_3.0.1_amd64.deb'];
        $this->assertSame(['linux', 'amd64', 'deb', 111], [$deb['os'], $deb['arch'], $deb['format'], $deb['size']]);
        $this->assertSame(hash('sha256', 'atglance_3.0.1_amd64.deb'), $deb['sha256']);
        $this->assertStringEndsWith('/downloads/files/cli/3.0.1/atglance_3.0.1_amd64.deb', $deb['url']);
        $this->assertNotEmpty($res->json('published_at'));
    }

    public function test_prerelease_is_older_than_its_release_and_numbers_compare_numerically(): void
    {
        $this->file('cli', '3.0.9', 'atglance_3.0.9_amd64.deb');
        $this->file('cli', '3.0.10-rc.1', 'atglance_3.0.10-rc.1_amd64.deb');
        $this->assertSame('3.0.10-rc.1', CliReleaseManifest::latest()['version']);

        $this->file('cli', '3.0.10', 'atglance_3.0.10_amd64.deb');
        $this->assertSame('3.0.10', CliReleaseManifest::latest()['version']);
    }

    public function test_no_release_is_404(): void
    {
        $this->getJson('/api/releases/cli/latest')
            ->assertNotFound()
            ->assertExactJson(['message' => 'No CLI release yet']);
    }
}
