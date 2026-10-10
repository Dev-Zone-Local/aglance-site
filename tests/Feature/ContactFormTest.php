<?php

namespace Tests\Feature;

use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Models\ContactMessage;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ContactMessageConfirmation;
use App\Notifications\ContactMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->flushHeaders();
    }

    private function valid(array $override = []): array
    {
        return [
            'name' => 'Asha Rao',
            'email' => 'asha@example.com',
            'topic' => 'support',
            'product' => 'console',
            'subject' => 'Console shows no styles',
            'message' => "My domain loads without CSS.\nIP:8000 works fine.",
            ...$override,
        ];
    }

    public function test_form_is_shown_and_prefilled_for_signed_in_users(): void
    {
        $this->get('/contact')->assertOk()->assertSee('Send us a message')->assertSee('name="message"', false);

        $user = User::factory()->create(['name' => 'Ravi', 'email' => 'ravi@example.com']);
        $this->actingAs($user)->get('/contact?topic=bug&product=cli')->assertOk()
            ->assertSee('value="ravi@example.com"', false)
            ->assertSee('<option value="bug" selected', false)
            ->assertSee('<option value="cli" selected', false);
    }

    public function test_message_is_saved_and_emailed_to_support_and_sender(): void
    {
        Notification::fake();

        $this->post('/contact', $this->valid())
            ->assertRedirect(route('contact').'#contact-form')
            ->assertSessionHas('sent_reference', '#00001');

        $m = ContactMessage::sole();
        $this->assertSame('new', $m->status);
        $this->assertSame('console', $m->product);
        $this->assertNotNull($m->emailed_at);

        Notification::assertSentTo(new AnonymousNotifiable, ContactMessageReceived::class,
            function ($n, $channels, $notifiable) use ($m) {
                $mail = $n->toMail($notifiable);

                return $notifiable->routes['mail'] === 'support@atglance.live'
                    && $n->contact->is($m)
                    && $mail->replyTo[0][0] === 'asha@example.com'
                    && str_contains($mail->subject, '#00001');
            });
        Notification::assertSentTo(new AnonymousNotifiable, ContactMessageConfirmation::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'asha@example.com');
    }

    public function test_recipient_comes_from_contact_settings(): void
    {
        Notification::fake();
        Setting::put(Setting::CONTACT, ['email' => 'hi@x.test', 'form_recipient' => 'help@x.test']);

        $this->post('/contact', $this->valid())->assertRedirect();

        Notification::assertSentTo(new AnonymousNotifiable, ContactMessageReceived::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'help@x.test');
    }

    public function test_message_is_kept_when_email_fails(): void
    {
        config(['mail.default' => 'failover', 'mail.mailers.failover' => ['transport' => 'failover', 'mailers' => ['nope']], 'mail.mailers.nope' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1]]);

        $this->post('/contact', $this->valid())->assertRedirect();

        $this->assertNull(ContactMessage::sole()->emailed_at);
    }

    public function test_validation_honeypot_and_hidden_form(): void
    {
        Notification::fake();

        $this->post('/contact', $this->valid(['email' => 'not-an-email', 'message' => 'short', 'topic' => 'bogus']))
            ->assertSessionHasErrors(['email', 'message', 'topic']);

        $this->post('/contact', $this->valid(['website' => 'http://spam.example']))->assertRedirect();
        $this->assertSame(0, ContactMessage::count());
        Notification::assertNothingSent();

        Setting::put(Setting::CONTACT, ['email' => 'hi@x.test', 'show_form' => false]);
        $this->get('/contact')->assertOk()->assertDontSee('name="message"', false);
        $this->post('/contact', $this->valid())->assertNotFound();
    }

    public function test_form_is_rate_limited(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/contact', $this->valid())->assertRedirect();
        }
        $this->post('/contact', $this->valid())->assertStatus(429);
    }

    public function test_admin_inbox_lists_views_and_updates_messages(): void
    {
        $admin = User::factory()->admin()->create();
        $m = ContactMessage::create($this->valid());

        $this->actingAs(User::factory()->create())->get('/admin/messages')->assertForbidden();
        $this->actingAs($admin)->get('/admin/messages')->assertOk()->assertSee('Console shows no styles');
        $this->actingAs($admin)->get("/admin/messages/{$m->id}")->assertOk()
            ->assertSee('asha@example.com')
            ->assertSee('IP:8000 works fine.');

        Livewire::actingAs($admin)->test(ViewContactMessage::class, ['record' => $m->getKey()])
            ->callAction('update', data: ['status' => 'in_progress', 'admin_notes' => 'Asked for nginx config'])
            ->assertHasNoActionErrors()
            ->callAction('resolve');

        $m->refresh();
        $this->assertSame('resolved', $m->status);
        $this->assertSame('Asked for nginx config', $m->admin_notes);

        Livewire::actingAs($admin)->test(ListContactMessages::class)->assertOk();
    }
}
