<?php

namespace Tests\Feature;

use App\Filament\Pages\ContactSettings;
use App\Filament\Pages\DownloadSettings;
use App\Filament\Pages\ProductPages;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ReleasePublishedNotification;
use App\Support\Downloads;
use App\Support\ProductShowcase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReleaseNotesScreenshotsContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->flushHeaders();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_publishes_release_with_title_type_summary_and_markdown_notes(): void
    {
        Notification::fake();
        $subscriber = User::factory()->create();

        Livewire::actingAs($this->admin())->test(DownloadSettings::class)
            ->assertSet('data.cli.new_notes', Downloads::NOTES_TEMPLATE)
            ->set('data.console.new_version', '2.3.0')
            ->set('data.console.new_title', 'Windows installer')
            ->set('data.console.new_type', 'minor')
            ->set('data.console.new_released_at', '2026-10-01')
            ->set('data.console.new_summary', 'Install the console on Windows Server.')
            ->set('data.console.new_notes', "### New\n- Windows installer\n\n### Fixed\n- Heartbeat retry")
            ->set('data.console.notify', true)
            ->call('save')
            ->assertHasNoErrors();

        $r = Downloads::all()['console']['releases'][0];
        $this->assertSame('2.3.0', $r['version']);
        $this->assertSame('Windows installer', $r['title']);
        $this->assertSame('minor', $r['type']);
        $this->assertSame('Install the console on Windows Server.', $r['summary']);
        $this->assertStringStartsWith('2026-10-01', $r['released_at']);
        $this->assertStringContainsString('Heartbeat retry', $r['notes']);

        Notification::assertSentTo($subscriber, ReleasePublishedNotification::class,
            fn ($n) => $n->summary === 'Install the console on Windows Server.'
                && str_contains($n->releaseNotesUrl, '/releases?product=console#console-2-3-0'));
    }

    public function test_untouched_template_is_not_saved_as_notes(): void
    {
        Downloads::publish('cli', '1.0.0', hash('sha256', 'x'), Downloads::NOTES_TEMPLATE);

        $this->assertNull(Downloads::all()['cli']['releases'][0]['notes']);
    }

    public function test_edit_release_updates_details(): void
    {
        Downloads::publish('console', '2.0.0', null, 'old');

        Livewire::actingAs($this->admin())->test(DownloadSettings::class)
            ->callAction('editRelease', data: [
                'version' => '2.0.0', 'title' => 'Big one', 'type' => 'security',
                'summary' => 'Patch now.', 'notes' => "- CVE fix", 'released_at' => '2026-09-15',
            ], arguments: ['product' => 'console', 'index' => 0])
            ->assertHasNoActionErrors();

        $r = Downloads::all()['console']['releases'][0];
        $this->assertSame(['Big one', 'security', 'Patch now.', '- CVE fix'], [$r['title'], $r['type'], $r['summary'], $r['notes']]);
        $this->assertStringStartsWith('2026-09-15', $r['released_at']);
    }

    public function test_public_release_notes_page_renders_markdown_and_filters(): void
    {
        Downloads::publish('cli', '2.1.0', hash('sha256', 'cli'), "### Fixed\n- **Faster** discovery", ['title' => 'Speed', 'type' => 'patch']);
        Downloads::publish('console', '3.0.0', null, '- Console thing <script>alert(1)</script>');

        $this->get('/releases')
            ->assertOk()
            ->assertSee('v2.1.0')
            ->assertSee('v3.0.0')
            ->assertSee('<strong>Faster</strong>', false)
            ->assertSee('Bug fix')
            ->assertSee('id="cli-2-1-0"', false)
            ->assertDontSee('<script>alert(1)</script>', false);

        $this->get('/releases?product=cli')->assertOk()->assertSee('v2.1.0')->assertDontSee('v3.0.0');
        $this->get('/releases?product=bogus')->assertOk()->assertSee('v3.0.0');
    }

    public function test_product_pages_show_latest_release_link(): void
    {
        Downloads::publish('console', '3.1.0', null, null, ['title' => 'Dark mode']);

        $this->get('/console')->assertOk()->assertSee('v3.1.0')->assertSee('Dark mode')
            ->assertSee('/releases?product=console#console-3-1-0', false);
    }

    public function test_contact_settings_new_fields_show_on_contact_page_and_footer(): void
    {
        Livewire::actingAs($this->admin())->test(ContactSettings::class)
            ->set('data.headline', 'Say hello.')
            ->set('data.email', 'hi@atglance.live')
            ->set('data.security_email', 'security@atglance.live')
            ->set('data.phone', '+91 98765 43210')
            ->set('data.support_hours', 'Mon–Fri 09–18 IST')
            ->set('data.response_time', 'Reply within a day')
            ->set('data.company_name', 'AtGlance Labs')
            ->set('data.address', "1 Main Road\nPune")
            ->set('data.linkedin', 'https://www.linkedin.com/company/atglance')
            ->set('data.show_social_in_footer', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('security@atglance.live', Setting::get(Setting::CONTACT)['security_email']);

        $this->get('/contact')->assertOk()
            ->assertSee('Say hello.')
            ->assertSee('mailto:security@atglance.live', false)
            ->assertSee('tel:+919876543210', false)
            ->assertSee('Mon–Fri 09–18 IST')
            ->assertSee('Reply within a day')
            ->assertSee('AtGlance Labs')
            ->assertSee('https://www.linkedin.com/company/atglance', false);

        // Footer social icon on every page.
        $this->get('/pricing')->assertOk()->assertSee('aria-label="LinkedIn"', false);
    }

    public function test_hidden_address_and_footer_socials(): void
    {
        Setting::put(Setting::CONTACT, [
            'email' => 'hi@x.test', 'address' => 'Secret street', 'show_address' => false,
            'github' => 'https://github.com/atglance', 'show_social_in_footer' => false,
        ]);

        $this->get('/contact')->assertOk()->assertDontSee('Secret street')->assertSee('https://github.com/atglance', false);
        $this->get('/pricing')->assertOk()->assertDontSee('aria-label="GitHub"', false);
    }

    public function test_product_pages_show_defaults_and_admin_hints(): void
    {
        $this->get('/console')->assertOk()
            ->assertSee('Every server config, every change')
            ->assertSee('id="ai-configuration-review"', false)
            ->assertDontSee('Screenshot needed');
        $this->get('/cli')->assertOk()->assertSee('Server health and config backups')->assertSee('Flag catalog');

        $this->actingAs($this->admin())->get('/console')->assertOk()
            ->assertSee('Screenshot needed')
            ->assertSee('Vulnerabilities Identified table');
    }

    public function test_admin_edits_product_page_text_images_and_blocks(): void
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->image('dash.png', 1600, 1000);

        $page = Livewire::actingAs($this->admin())->test(ProductPages::class)
            ->assertSet('data.console.hero.title', ProductShowcase::defaults('console')['hero']['title'])
            ->set('data.console.hero.title', 'Your configs, your servers.')
            ->set('data.console.hero.image', $image)
            ->set('data.console.sections.rbac', false)
            ->set('data.cli.hero.badge', 'CLI badge text');

        // Edit the first feature and hide the second.
        $keys = array_keys($page->get('data.console.features'));
        $page->set("data.console.features.{$keys[0]}.title", 'Fleet dashboard')
            ->set("data.console.features.{$keys[1]}.visible", false)
            ->call('save')
            ->assertHasNoErrors();

        $saved = ProductShowcase::get('console');
        $this->assertSame('Your configs, your servers.', $saved['hero']['title']);
        $this->assertStringStartsWith('showcase/', $saved['hero']['image']);
        Storage::disk('public')->assertExists($saved['hero']['image']);
        $this->assertSame('Fleet dashboard', $saved['features'][0]['title']);
        $this->assertTrue(array_is_list($saved['features']));
        $this->assertTrue(array_is_list($saved['features'][0]['bullets']));

        $this->get('/console')->assertOk()
            ->assertSee('Your configs, your servers.')
            ->assertSee('Fleet dashboard')
            ->assertDontSee('Every registered server, searchable')
            ->assertDontSee('RBAC matrix')
            ->assertSee(ProductShowcase::imageUrl($saved['hero']['image']), false);
        $this->get('/cli')->assertOk()->assertSee('CLI badge text');

        // Reset removes the uploaded image and restores the defaults.
        ProductShowcase::reset('console');
        Storage::disk('public')->assertMissing($saved['hero']['image']);
        $this->get('/console')->assertOk()->assertSee('Every registered server, searchable');
    }

    public function test_product_pages_admin_is_admin_only(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/product-pages')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/product-pages')->assertOk()->assertSee('Feature sections');
    }
}
