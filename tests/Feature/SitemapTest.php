<?php

namespace Tests\Feature;

use App\Models\Doc;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_lists_public_pages_docs_and_cms_pages(): void
    {
        config(['atglance.frontend_url' => 'https://atglance.live/']);
        Doc::create(['slug' => 'quickstart', 'title' => 'Quickstart', 'section' => 'Start', 'content' => 'x', 'order' => 1]);
        Page::create(['slug' => 'about', 'title' => 'About', 'content' => 'x']);
        Page::create(['slug' => 'internal', 'title' => 'Internal', 'content' => 'x']);

        $xml = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString('<loc>https://atglance.live/</loc>', $xml);
        $this->assertStringContainsString('<loc>https://atglance.live/pricing</loc>', $xml);
        $this->assertStringContainsString('<loc>https://atglance.live/docs/quickstart</loc>', $xml);
        $this->assertStringContainsString('<loc>https://atglance.live/about</loc>', $xml);
        // Only pages with a public SPA route; never private areas.
        $this->assertStringNotContainsString('/internal', $xml);
        $this->assertStringNotContainsString('/dashboard', $xml);
        $this->assertStringNotContainsString('/admin', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_robots_points_to_sitemap_and_blocks_private_areas(): void
    {
        config(['atglance.frontend_url' => 'https://atglance.live']);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap: https://atglance.live/sitemap.xml', false)
            ->assertSee('Disallow: /admin', false)
            ->assertSee('Disallow: /dashboard', false);
    }

    public function test_command_writes_static_files(): void
    {
        config(['atglance.frontend_url' => 'https://atglance.live']);
        Doc::create(['slug' => 'quickstart', 'title' => 'Quickstart', 'section' => 'Start', 'content' => 'x', 'order' => 1]);
        $dir = sys_get_temp_dir().'/atglance-sitemap-'.uniqid();
        mkdir($dir);

        $this->artisan('atglance:sitemap', ['--path' => $dir])->assertSuccessful();

        $this->assertStringContainsString('<loc>https://atglance.live/docs/quickstart</loc>', file_get_contents("{$dir}/sitemap.xml"));
        $this->assertStringContainsString('Sitemap: https://atglance.live/sitemap.xml', file_get_contents("{$dir}/robots.txt"));
        $this->assertFileDoesNotExist("{$dir}/sitemap.xml.tmp");

        array_map('unlink', glob("{$dir}/*"));
        rmdir($dir);
    }

    public function test_saving_content_in_tests_never_writes_to_public(): void
    {
        $before = file_exists(public_path('sitemap.xml')) ? filemtime(public_path('sitemap.xml')) : null;

        Doc::create(['slug' => 'x', 'title' => 'X', 'section' => 'S', 'content' => 'x', 'order' => 1]);

        $this->assertSame($before, file_exists(public_path('sitemap.xml')) ? filemtime(public_path('sitemap.xml')) : null);
    }
}
