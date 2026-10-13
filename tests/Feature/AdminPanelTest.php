<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->withHeaders(['Accept' => 'text/html'])->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->withHeaders(['Accept' => 'text/html'])
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_can_open_every_panel_page(): void
    {
        $this->seed(ContentSeeder::class);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->withHeaders(['Accept' => 'text/html']);

        foreach ([
            '/admin',
            '/admin/pricing-plans',
            '/admin/pricing-plans/1/edit',
            '/admin/docs',
            '/admin/docs/1/edit',
            '/admin/faqs',
            '/admin/pages',
            '/admin/users',
            '/admin/contact-settings',
            '/admin/download-settings',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
