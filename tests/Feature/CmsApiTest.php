<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    public function test_root_reports_service(): void
    {
        $this->getJson('/api')->assertOk()->assertJson(['service' => 'AtGlance API', 'ok' => true]);
    }

    public function test_pricing_is_ordered_with_features_array(): void
    {
        $this->getJson('/api/cms/pricing')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.name', 'Free')
            ->assertJsonPath('0.highlighted', true)
            ->assertJsonStructure([['id', 'name', 'price', 'period', 'description', 'features', 'cta', 'highlighted', 'order']]);
    }

    public function test_faqs_list(): void
    {
        $this->getJson('/api/cms/faqs')
            ->assertOk()
            ->assertJsonCount(6)
            ->assertJsonStructure([['id', 'question', 'answer', 'category', 'order']]);
    }

    public function test_docs_list_omits_content(): void
    {
        $res = $this->getJson('/api/cms/docs')->assertOk()->assertJsonPath('0.slug', 'quickstart');

        foreach ($res->json() as $doc) {
            $this->assertArrayNotHasKey('content', $doc);
        }
    }

    public function test_doc_by_slug(): void
    {
        $this->getJson('/api/cms/docs/quickstart')
            ->assertOk()
            ->assertJsonPath('slug', 'quickstart')
            ->assertJsonStructure(['slug', 'title', 'section', 'content', 'order']);

        $this->getJson('/api/cms/docs/nope')->assertNotFound()->assertJsonPath('message', 'Doc not found');
    }

    public function test_contact_settings(): void
    {
        $this->getJson('/api/cms/contact')
            ->assertOk()
            ->assertJsonPath('email', 'info@atglance.live')
            ->assertJsonStructure(['email', 'sales_email', 'support_email', 'address', 'github', 'twitter']);
    }

    public function test_static_pages(): void
    {
        foreach (['about', 'terms', 'privacy'] as $slug) {
            $this->getJson("/api/cms/pages/{$slug}")
                ->assertOk()
                ->assertJsonPath('slug', $slug)
                ->assertJsonStructure(['slug', 'title', 'content']);
        }

        $this->getJson('/api/cms/pages/nope')->assertNotFound();
    }

    public function test_downloads_require_login(): void
    {
        $this->getJson('/api/downloads')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/downloads')
            ->assertOk()
            ->assertJsonStructure(['cli_url', 'cli_version', 'console_url', 'console_version', 'cli_install_command']);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(ContentSeeder::class);

        $this->getJson('/api/cms/pricing')->assertJsonCount(2);
        $this->getJson('/api/cms/faqs')->assertJsonCount(6);
    }

    public function test_cors_rejects_unknown_origin(): void
    {
        // A single configured origin is always echoed as-is, so the browser blocks any other origin.
        $evil = $this->withHeaders(['Origin' => 'https://evil.test'])->getJson('/api/cms/faqs');
        $this->assertNotSame('https://evil.test', $evil->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $evil->headers->get('Access-Control-Allow-Origin'));

        $this->withHeaders(['Origin' => 'http://localhost:3000']);

        $this->getJson('/api/cms/faqs')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }
}
