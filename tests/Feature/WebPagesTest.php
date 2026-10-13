<?php

namespace Tests\Feature;

use App\Models\Doc;
use App\Models\Faq;
use App\Models\Page;
use App\Models\PricingPlan;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Blade website: public pages, docs and CMS pages render with the seeded content. */
class WebPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Plain browser requests (the base TestCase sends JSON headers for API tests).
        $this->flushHeaders();
        $this->withoutVite();
        $this->seed(ContentSeeder::class);
    }

    public function test_marketing_pages_render(): void
    {
        $pages = [
            '/' => 'Operations at a glance',
            '/product' => 'Two surfaces. One source of truth.',
            '/cli' => 'Server health and config backups',
            '/console' => 'Every server config, every change',
            '/architecture' => "Everything you need. Nothing you don't.",
            '/use-cases' => 'Built for the teams who get paged.',
            '/security' => 'Quiet by default. Auditable by design.',
        ];

        foreach ($pages as $url => $text) {
            $this->get($url)->assertOk()->assertSee($text);
        }
    }

    public function test_cms_backed_pages_show_database_content(): void
    {
        $plan = PricingPlan::orderBy('order')->first();
        $faq = Faq::first();
        $doc = Doc::orderBy('order')->first();

        $this->get('/pricing')->assertOk()->assertSee($plan->name);
        $this->get('/faq')->assertOk()->assertSee($faq->question);
        $this->get('/docs')->assertOk()->assertSee($doc->title);
        $this->get("/docs/{$doc->slug}")->assertOk()->assertSee($doc->section);
        $this->get('/contact')->assertOk()->assertSee('Talk to a real engineer.');

        foreach (['about', 'terms', 'privacy'] as $slug) {
            $this->get("/{$slug}")->assertOk();
        }
    }

    public function test_unknown_pages_are_404(): void
    {
        $this->get('/docs/does-not-exist')->assertNotFound()->assertSee('This page does not exist.');
        $this->get('/no-such-page')->assertNotFound();
    }

    public function test_markdown_strips_raw_html_and_unsafe_links(): void
    {
        Page::where('slug', 'about')->update(['content' => "# Hi\n\n<script>alert(1)</script>\n\n[x](javascript:alert(1))"]);

        $this->get('/about')->assertOk()
            ->assertSee('<h1>Hi</h1>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('javascript:alert', false);
    }

    public function test_header_shows_sign_in_for_guests_and_account_menu_for_users(): void
    {
        $this->get('/')->assertSee('Sign up free')->assertDontSee('Sign out');

        $this->actingAs(User::factory()->create(['name' => 'Jane Ops']))
            ->get('/')->assertSee('Jane Ops')->assertSee('Sign out');
    }
}
