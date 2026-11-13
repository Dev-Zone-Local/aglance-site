<?php

namespace Tests\Feature;

use App\Filament\Pages\BlogSettings;
use App\Filament\Pages\SeoSettings;
use App\Filament\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Models\BlogPost;
use App\Models\ContactMessage;
use App\Models\Setting;
use App\Models\User;
use App\Support\AdminStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminDashboardSeoBlogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function makePost(array $attrs = []): BlogPost
    {
        return BlogPost::create([
            'title' => 'How we back up unit files',
            'body' => "## Why\nBecause servers change.",
            'status' => 'published',
            'published_at' => now()->subDay(),
            'tags' => ['how-to'],
            ...$attrs,
        ]);
    }

    public function test_dashboard_counts_users_by_plan_and_messages(): void
    {
        User::factory()->count(3)->create(['plan' => 'free']);
        User::factory()->count(2)->create(['plan' => 'enterprise']);
        ContactMessage::create(['name' => 'Ana', 'email' => 'ana@example.com', 'topic' => 'support', 'subject' => 'Help with install', 'message' => 'x', 'status' => 'new']);
        ContactMessage::create(['name' => 'Bo', 'email' => 'bo@example.com', 'topic' => 'sales', 'subject' => 'Pricing', 'message' => 'x', 'status' => 'resolved']);

        $s = AdminStats::all();
        $this->assertSame(3, $s['users']['free']);
        $this->assertSame(2, $s['users']['enterprise']);
        $this->assertSame(1, $s['messages']['new']);
        $this->assertSame(2, $s['messages']['total']);
        $this->assertCount(30, $s['users']['daily']);
        $this->assertSame(['Sign-ups', 'Licences', 'Activations', 'Licence files', 'Messages'], array_keys($s['heatmap']['rows']));
        $this->assertCount(6, $s['trend']['labels']);

        $this->actingAs($this->admin())->get('/admin')->assertOk()
            ->assertSee('Licences in use')->assertSee('Free plan')->assertSee('Enterprise plan')
            ->assertSee('Help with install')->assertSee('Growth, last 6 months');
    }

    public function test_seo_settings_change_titles_robots_and_sitemap(): void
    {
        Livewire::actingAs($this->admin())->test(SeoSettings::class)
            ->set('data.site_name', 'AG')
            ->set('data.default_description', 'Default text for search.')
            ->set('data.google_verification', 'abc123')
            ->set('data.robots_disallow', "/known-problems\n")
            ->set('data.pages', [['path' => 'pricing', 'title' => 'Plans and prices', 'description' => 'What it costs.', 'noindex' => false, 'image' => null],
                ['path' => '/use-cases', 'title' => null, 'description' => null, 'noindex' => true, 'image' => null]])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('/pricing', Setting::get(Setting::SEO)['pages'][0]['path']);

        $this->get('/faq')->assertOk()->assertSee('<title>FAQ · AG</title>', false)
            ->assertSee('<meta name="google-site-verification" content="abc123">', false);
        $this->get('/pricing')->assertOk()->assertSee('<title>Plans and prices</title>', false)->assertSee('What it costs.');
        $this->get('/use-cases')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /known-problems')->assertSee('Disallow: /admin');
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/use-cases</loc>', false)->assertSee('/pricing</loc>', false);
    }

    public function test_turning_indexing_off_blocks_everything(): void
    {
        Setting::put(Setting::SEO, ['allow_indexing' => false]);

        $this->get('/')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->assertStringContainsString("Disallow: /\n", $this->get('/robots.txt')->getContent());
    }

    public function test_public_blog_lists_published_posts_only(): void
    {
        $this->makePost();
        $this->makePost(['title' => 'Draft idea', 'status' => 'draft', 'published_at' => null]);
        $this->makePost(['title' => 'Next week', 'published_at' => now()->addWeek()]);

        $this->get('/blog')->assertOk()->assertSee('How we back up unit files')->assertDontSee('Draft idea')->assertDontSee('Next week');
        $this->get('/blog?tag=how-to')->assertOk()->assertSee('How we back up unit files');
        $this->get('/blog/how-we-back-up-unit-files')->assertOk()->assertSee('Because servers change.')->assertSee('og:type" content="article"', false)
            ->assertSee('Copy link')
            ->assertSee('https://www.linkedin.com/sharing/share-offsite/?url='.rawurlencode(route('blog.show', 'how-we-back-up-unit-files')), false)
            ->assertSee('https://twitter.com/intent/tweet?url=', false)
            ->assertSee('rel="noopener noreferrer"', false);
        $this->get('/blog/draft-idea')->assertNotFound();
        // Drafts are not shareable: no share buttons on a preview.
        $this->actingAs($this->admin())->get('/blog/draft-idea')->assertOk()->assertSee('Preview')->assertSee('noindex', false)->assertDontSee('Copy link');

        $xml = simplexml_load_string($this->get('/blog/rss.xml')->assertOk()->getContent());
        $this->assertCount(1, $xml->channel->item);
        $this->get('/sitemap.xml')->assertSee('/blog/how-we-back-up-unit-files', false)->assertDontSee('draft-idea');
    }

    public function test_blog_link_in_nav_and_blog_can_be_turned_off(): void
    {
        $this->get('/')->assertDontSee('>Blog<', false); // no posts yet
        $this->makePost();
        $this->get('/')->assertSee('>Blog<', false);

        Livewire::actingAs($this->admin())->test(BlogSettings::class)
            ->set('data.enabled', false)
            ->call('save')
            ->assertHasNoErrors();
        auth()->logout();

        $this->get('/blog')->assertNotFound();
        $this->get('/blog/how-we-back-up-unit-files')->assertNotFound();
        $this->get('/')->assertDontSee('>Blog<', false);
    }

    public function test_admin_creates_a_post_with_slug_and_author(): void
    {
        $admin = $this->admin();
        Livewire::actingAs($admin)->test(CreateBlogPost::class)
            ->fillForm(['title' => 'AtGlance 3.0 is out', 'body' => 'Agents everywhere.', 'status' => 'published'])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = BlogPost::firstOrFail();
        $this->assertSame('atglance-30-is-out', $post->slug);
        $this->assertSame($admin->id, $post->author_id);
        $this->assertTrue($post->isPublic());
    }
}
