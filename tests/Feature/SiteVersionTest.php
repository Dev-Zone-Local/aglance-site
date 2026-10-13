<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SiteVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** "Site version" at the bottom of the admin sidebar: VERSION file + the deployed git commit. */
class SiteVersionTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flushHeaders();
        $this->withoutVite();
        $this->root = sys_get_temp_dir().'/ag-version-'.uniqid();
        File::ensureDirectoryExists($this->root.'/.git/refs/heads');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_reads_version_and_branch_commit(): void
    {
        File::put($this->root.'/VERSION', "2.1.0\n");
        File::put($this->root.'/.git/HEAD', "ref: refs/heads/Version2.1\n");
        File::put($this->root.'/.git/refs/heads/Version2.1', "5352f9f0123456789abcdef0123456789abcdef0\n");

        $v = SiteVersion::read($this->root);

        $this->assertSame('2.1.0', $v['version']);
        $this->assertSame('5352f9f', $v['commit']);
        $this->assertSame('Version2.1', $v['branch']);
    }

    public function test_reads_packed_refs_and_detached_head(): void
    {
        File::put($this->root.'/.git/HEAD', "ref: refs/heads/main\n");
        File::put($this->root.'/.git/packed-refs', "# pack-refs with: peeled\nabcdef1234567890abcdef1234567890abcdef12 refs/heads/main\n");
        $this->assertSame('abcdef1', SiteVersion::read($this->root)['commit']);

        File::put($this->root.'/.git/HEAD', "0123456789abcdef0123456789abcdef01234567\n");
        $v = SiteVersion::read($this->root);
        $this->assertSame('0123456', $v['commit']);
        $this->assertNull($v['branch']);
    }

    public function test_without_version_file_or_git(): void
    {
        $v = SiteVersion::read($this->root.'/missing');

        $this->assertSame('dev', $v['version']);
        $this->assertNull($v['commit']);
        $this->assertSame('dev', SiteVersion::label($v));
    }

    public function test_admin_sidebar_groups_are_in_order(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk()->getContent();

        $positions = array_map(fn ($g) => strpos($html, '>'.$g.'<') ?: strpos($html, $g), ['Support', 'Access', 'Content', 'Settings']);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'Sidebar order: Support, Access, Content, Settings');
    }

    public function test_admin_sidebar_shows_the_site_version_on_every_page(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach (['/admin', '/admin/captcha-settings'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('class="ag-site-version"', false)
                ->assertSee('Site version')
                ->assertSee(SiteVersion::current()['version']);
        }
    }
}
