<?php

namespace Tests\Feature;

use App\Filament\Pages\BlogSettings;
use App\Http\Controllers\Api\BlogPostApiController;
use App\Models\BlogApiKey;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Blog API (/api/blog) used by n8n: keys, create, update, publish, images, delete. */
class BlogApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->admin()->create(['name' => 'Ana Admin']);
        [, $this->key] = BlogApiKey::issue('n8n', $this->admin);
    }

    protected function tearDown(): void
    {
        BlogPostApiController::$resolver = null;
        parent::tearDown();
    }

    private function api(): static
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$this->key, 'Accept' => 'application/json']);
    }

    /** A real 1x1 PNG. */
    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    }

    public function test_requires_a_valid_key(): void
    {
        $this->postJson('/api/blog/posts', ['title' => 'x', 'body' => 'y'])->assertUnauthorized()->assertHeader('WWW-Authenticate', 'Bearer');
        $this->withHeaders(['Authorization' => 'Bearer agb_wrong'])->postJson('/api/blog/posts', [])->assertUnauthorized();
        // A user's or licence's Sanctum token is not a blog key.
        $this->withHeaders(['Authorization' => 'Bearer 1|abcdef'])->getJson('/api/blog/posts')->assertUnauthorized();
        $this->assertSame(0, BlogPost::count());
    }

    public function test_creates_a_draft_and_publishes_it(): void
    {
        $res = $this->api()->postJson('/api/blog/posts', [
            'title' => 'Agents are here',
            'body' => "## Hello\nThe agent connects out.",
            'tags' => 'release, agent',
            'excerpt' => 'Short summary.',
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'agents-are-here')
            ->assertJsonPath('data.state', 'draft')
            ->assertJsonPath('data.tags', ['release', 'agent'])
            ->assertJsonPath('data.author', 'Ana Admin');

        $id = $res->json('data.id');
        $this->get('/blog/agents-are-here')->assertNotFound();

        $this->api()->postJson("/api/blog/posts/{$id}/publish")->assertOk()->assertJsonPath('data.state', 'published');
        $this->get('/blog/agents-are-here')->assertOk()->assertSee('The agent connects out.');

        $this->api()->postJson('/api/blog/posts/agents-are-here/unpublish')->assertOk()->assertJsonPath('data.state', 'draft');
        $this->assertNotNull(BlogApiKey::first()->last_used_at);
    }

    public function test_creates_published_in_one_call_and_schedules(): void
    {
        $this->api()->postJson('/api/blog/posts', ['title' => 'Now', 'body' => 'x', 'status' => 'published'])
            ->assertCreated()->assertJsonPath('data.state', 'published');
        $this->api()->postJson('/api/blog/posts', ['title' => 'Later', 'body' => 'x', 'status' => 'published', 'published_at' => now()->addDay()->toIso8601String()])
            ->assertCreated()->assertJsonPath('data.state', 'scheduled');

        $this->api()->getJson('/api/blog/posts?status=scheduled')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Later');
        $this->api()->getJson('/api/blog/posts')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonMissingPath('data.0.body');
    }

    public function test_validation_and_duplicate_slug(): void
    {
        $this->api()->postJson('/api/blog/posts', [])->assertUnprocessable()->assertJsonValidationErrors(['title', 'body']);
        $this->api()->postJson('/api/blog/posts', ['title' => 'Same', 'body' => 'x'])->assertCreated();
        $this->api()->postJson('/api/blog/posts', ['title' => 'Other', 'slug' => 'same', 'body' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors(['slug']);
    }

    public function test_updates_by_slug_and_deletes(): void
    {
        $id = $this->api()->postJson('/api/blog/posts', ['title' => 'Draft one', 'body' => 'old'])->json('data.id');

        $this->api()->patchJson('/api/blog/posts/draft-one', ['body' => 'new body', 'seo_title' => 'SEO title'])
            ->assertOk()->assertJsonPath('data.body', 'new body')->assertJsonPath('data.title', 'Draft one')->assertJsonPath('data.seo_title', 'SEO title');
        $this->api()->getJson("/api/blog/posts/{$id}")->assertOk()->assertJsonPath('data.body', 'new body');

        $this->api()->deleteJson("/api/blog/posts/{$id}")->assertOk();
        $this->api()->getJson("/api/blog/posts/{$id}")->assertNotFound();
    }

    public function test_cover_image_as_file_base64_and_url(): void
    {
        // File upload (multipart).
        $res = $this->api()->post('/api/blog/posts', [
            'title' => 'With file', 'body' => 'x',
            'cover_image' => UploadedFile::fake()->createWithContent('cover.png', $this->png()),
        ])->assertCreated();
        $this->assertStringContainsString('/storage/blog/', $res->json('data.cover_image_url'));

        // Base64 (data URI).
        $res = $this->api()->postJson('/api/blog/posts', ['title' => 'With base64', 'body' => 'x', 'cover_image_base64' => 'data:image/png;base64,'.base64_encode($this->png())])->assertCreated();
        $this->assertStringEndsWith('.png', $res->json('data.cover_image_url'));

        // Not an image.
        $this->api()->postJson('/api/blog/posts', ['title' => 'Bad', 'body' => 'x', 'cover_image_base64' => base64_encode('<?php echo 1;')])
            ->assertUnprocessable()->assertJsonValidationErrors(['cover_image_base64']);

        // An https link is downloaded once (DNS answer faked: no network in tests).
        BlogPostApiController::$resolver = fn (string $host) => $host === 'images.example.com' ? ['93.184.215.14'] : [$host];
        Http::fake(['images.example.com/*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);
        $this->api()->postJson('/api/blog/posts', ['title' => 'With link', 'body' => 'x', 'cover_image_url' => 'https://images.example.com/a.png'])->assertCreated();

        // Links inside private networks are refused.
        $this->api()->postJson('/api/blog/posts', ['title' => 'Inside', 'body' => 'x', 'cover_image_url' => 'https://127.0.0.1/a.png'])
            ->assertUnprocessable()->assertJsonValidationErrors(['cover_image_url']);
        $this->api()->postJson('/api/blog/posts', ['title' => 'Plain http', 'body' => 'x', 'cover_image_url' => 'http://images.example.com/a.png'])
            ->assertUnprocessable()->assertJsonValidationErrors(['cover_image_url']);
    }

    public function test_image_upload_for_the_body(): void
    {
        $this->api()->postJson('/api/blog/images', ['image_base64' => base64_encode($this->png()), 'alt' => 'Diagram'])
            ->assertCreated()->assertJsonPath('data.markdown', fn ($md) => str_starts_with($md, '![Diagram](') && str_contains($md, '/storage/blog/attachments/'));
        $this->api()->postJson('/api/blog/images', [])->assertUnprocessable();
    }

    public function test_admin_creates_and_revokes_keys(): void
    {
        $page = Livewire::actingAs($this->admin)->test(BlogSettings::class)
            ->callAction('createKey', data: ['name' => 'Second workflow'])
            ->assertHasNoActionErrors();

        $plain = $page->get('newKey');
        $this->assertStringStartsWith('agb_', $plain);
        $key = BlogApiKey::firstWhere('name', 'Second workflow');
        $this->assertSame(hash('sha256', $plain), $key->token_hash);
        $this->assertNotSame($plain, $key->token_hash);

        $this->withHeaders(['Authorization' => 'Bearer '.$plain])->getJson('/api/blog/posts')->assertOk();

        Livewire::actingAs($this->admin)->test(BlogSettings::class)->callAction('revokeKey', arguments: ['id' => $key->id]);
        $this->withHeaders(['Authorization' => 'Bearer '.$plain])->getJson('/api/blog/posts')->assertUnauthorized();
    }
}
