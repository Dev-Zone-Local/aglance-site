<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admins sign in on the site's /login page; the admin panel has no sign-in page of its own. */
class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withHeaders(['Accept' => 'text/html']);
    }

    public function test_admin_signs_in_on_the_site_page_and_lands_in_the_panel(): void
    {
        User::factory()->admin()->create(['email' => 'root@example.com', 'password' => 'secret123']);

        $this->post('/login', ['email' => 'root@example.com', 'password' => 'secret123'])->assertRedirect('/admin');
        $this->assertAuthenticated();
        $this->get('/admin')->assertOk();
    }

    public function test_guest_opening_the_panel_is_sent_to_the_site_login_and_back(): void
    {
        User::factory()->admin()->create(['email' => 'root@example.com', 'password' => 'secret123']);

        $this->get('/admin/download-settings')->assertRedirect('/login');
        $this->post('/login', ['email' => 'root@example.com', 'password' => 'secret123'])
            ->assertRedirect(url('/admin/download-settings'));
    }

    public function test_intended_page_outside_the_panel_is_ignored_for_admins(): void
    {
        User::factory()->admin()->create(['email' => 'root@example.com', 'password' => 'secret123']);

        $this->get('/dashboard')->assertRedirect('/login');
        $this->post('/login', ['email' => 'root@example.com', 'password' => 'secret123'])->assertRedirect('/admin');
    }

    public function test_old_admin_login_link_goes_to_the_site_login(): void
    {
        $this->get('/admin/login')->assertRedirect('/login')->assertStatus(301);
    }

    public function test_wrong_password_says_so(): void
    {
        User::factory()->admin()->create(['email' => 'root@example.com', 'password' => 'secret123']);

        $this->from('/login')->post('/login', ['email' => 'root@example.com', 'password' => 'nope'])
            ->assertRedirect('/login')->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_user_without_admin_access_is_refused_by_the_panel(): void
    {
        User::factory()->create(['email' => 'user@example.com', 'password' => 'secret123']);

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'secret123'])->assertRedirect(route('dashboard'));
        $this->get('/admin')->assertForbidden();
    }

    public function test_signed_in_admin_opening_login_goes_to_the_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/login')->assertRedirect('/admin');
    }
}
