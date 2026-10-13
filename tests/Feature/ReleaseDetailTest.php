<?php

namespace Tests\Feature;

use App\Support\Downloads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Releases in the main navigation, and one page per release (/releases/{product}/{version}). */
class ReleaseDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flushHeaders();
        $this->withoutVite();

        Downloads::save([
            'cli' => ['url' => 'https://dl.example/cli.deb', 'file_name' => 'atglance_all.deb', 'install_steps' => null, 'releases' => []],
            'console' => [],
        ]);
        Downloads::publish('cli', '2.0.0', hash('sha256', 'two'), "### Fixed\n- Old bug", ['released_at' => '2026-08-01']);
        Downloads::publish('cli', '2.1.0', hash('sha256', 'cli'), "### New\n- **Faster** discovery\n\n### Fixed\n- Timeout on slow hosts",
            ['title' => 'Speed', 'type' => 'minor', 'summary' => 'Discovery is twice as fast.', 'released_at' => '2026-09-01']);
        Downloads::publish('cli', '2.2.0', null, null, ['released_at' => '2026-10-01']);
    }

    public function test_navbar_has_releases_and_footer_has_pricing(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $nav = substr($html, strpos($html, 'aria-label="Main"'));
        $nav = substr($nav, 0, strpos($nav, '</nav>'));
        $this->assertStringContainsString(route('releases'), $nav);
        $this->assertStringContainsString('>Releases<', $nav);
        $this->assertStringNotContainsString(route('pricing'), $nav);

        $footer = substr($html, strpos($html, 'aria-label="Footer"'));
        $footer = substr($footer, 0, strpos($footer, '</nav>'));
        preg_match_all('#<a [^>]*>([^<]+)</a>#', $footer, $links);
        $this->assertSame(['About', 'FAQ', 'Pricing', 'Security', 'Terms', 'Privacy', 'Contact'], array_map('trim', $links[1]));

        // Highlighted on the list and on a release page.
        $this->assertMatchesRegularExpression(
            '#href="'.preg_quote(route('releases'), '#').'"\s+aria-current="page"#',
            $this->get('/releases/cli/2.1.0')->getContent(),
        );
    }

    public function test_list_links_every_release_to_its_page(): void
    {
        $this->get('/releases')->assertOk()
            ->assertSee('href="'.route('releases.show', ['cli', '2.1.0']).'"', false)
            ->assertSee('href="'.route('releases.show', ['cli', '2.0.0']).'"', false)
            ->assertSee('Discovery is twice as fast.')
            ->assertSee('id="cli-2-1-0"', false); // old #anchors keep working
    }

    public function test_release_page_shows_everything_about_the_release(): void
    {
        $this->get('/releases/cli/2.1.0')->assertOk()
            ->assertSee('<title>AtGlance CLI v2.1.0', false)
            ->assertSee('Speed')
            ->assertSee('Discovery is twice as fast.')
            ->assertSee('<strong>Faster</strong>', false)
            ->assertSee('Timeout on slow hosts')
            ->assertSee('01 Sep 2026')
            ->assertSee(hash('sha256', 'cli'))
            ->assertSee('sha256sum -c -')
            ->assertSee('atglance_all.deb')
            ->assertSee(route('dashboard.install', ['product' => 'cli']), false)
            // Newer and older release of the same product.
            ->assertSee('href="'.route('releases.show', ['cli', '2.2.0']).'"', false)
            ->assertSee('href="'.route('releases.show', ['cli', '2.0.0']).'"', false)
            ->assertDontSee('Latest release');
    }

    public function test_latest_release_is_marked_and_has_no_newer_link(): void
    {
        $this->get('/releases/cli/2.2.0')->assertOk()
            ->assertSee('Latest')
            ->assertSee('href="'.route('releases.show', ['cli', '2.1.0']).'"', false)
            ->assertSee('Maintenance release.');
    }

    public function test_unknown_release_or_product_is_404(): void
    {
        $this->get('/releases/cli/9.9.9')->assertNotFound();
        $this->get('/releases/bogus/2.1.0')->assertNotFound();
    }

    public function test_release_pages_are_in_the_sitemap(): void
    {
        $this->get('/sitemap.xml')->assertOk()
            ->assertSee('/releases/cli/2.1.0</loc>', false)
            ->assertSee('/releases/cli/2.0.0</loc>', false);
    }

    public function test_other_pages_link_to_the_release_page(): void
    {
        $this->get('/cli')->assertOk()->assertSee('href="'.route('releases.show', ['cli', '2.2.0']).'"', false);
    }
}
