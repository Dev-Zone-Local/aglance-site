<?php

namespace Tests\Feature;

use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Mail\ContactReplyMail;
use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ContactMessageConfirmation;
use App\Notifications\ContactMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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

    private function signIn(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_guests_see_sign_in_prompt_instead_of_form(): void
    {
        $this->get('/contact')->assertOk()
            ->assertSee('Sign in to send us a message')
            ->assertSee('href="'.route('login').'"', false)
            ->assertDontSee('name="message"', false);
        $this->assertSame(route('contact').'#contact-form', session('url.intended'));

        $this->post('/contact', $this->valid())->assertRedirect(route('login'));
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_form_is_shown_and_prefilled_for_signed_in_users(): void
    {
        $user = User::factory()->create(['name' => 'Ravi', 'email' => 'ravi@example.com']);
        $this->actingAs($user)->get('/contact?topic=bug&product=cli')->assertOk()
            ->assertSee('Send us a message')->assertSee('name="message"', false)
            ->assertSee('value="ravi@example.com"', false)
            ->assertSee('<option value="bug" selected', false)
            ->assertSee('<option value="cli" selected', false);
    }

    public function test_message_is_saved_and_emailed_to_support_and_sender(): void
    {
        $user = $this->signIn();
        Notification::fake();

        $this->post('/contact', $this->valid())
            ->assertRedirect(route('contact').'#contact-form')
            ->assertSessionHas('sent_reference', '#00001');

        $m = ContactMessage::sole();
        $this->assertSame('new', $m->status);
        $this->assertSame('console', $m->product);
        $this->assertSame($user->id, $m->user_id);
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
        $this->signIn();
        Notification::fake();
        Setting::put(Setting::CONTACT, ['email' => 'hi@x.test', 'form_recipient' => 'help@x.test']);

        $this->post('/contact', $this->valid())->assertRedirect();

        Notification::assertSentTo(new AnonymousNotifiable, ContactMessageReceived::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'help@x.test');
    }

    public function test_message_is_kept_when_email_fails(): void
    {
        $this->signIn();
        config(['mail.default' => 'failover', 'mail.mailers.failover' => ['transport' => 'failover', 'mailers' => ['nope']], 'mail.mailers.nope' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1]]);

        $this->post('/contact', $this->valid())->assertRedirect();

        $this->assertNull(ContactMessage::sole()->emailed_at);
    }

    public function test_validation_honeypot_and_hidden_form(): void
    {
        $this->signIn();
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

    public function test_captcha_protects_the_form_when_enabled(): void
    {
        Notification::fake();
        $this->signIn();
        $this->enableCaptcha();
        $score = 0.9;
        Http::fake(['www.google.com/recaptcha/api/siteverify' => function ($request) use (&$score) {
            return Http::response(['success' => true, 'score' => $score, 'action' => explode(':', $request['response'])[0]]);
        }]);

        $this->get('/contact')->assertOk()->assertSee('data-recaptcha-action="contact"', false);

        $this->post('/contact', $this->valid())->assertSessionHasErrors('g-recaptcha-response');
        $this->post('/contact', $this->valid(['g-recaptcha-response' => 'login:x']))->assertSessionHasErrors('g-recaptcha-response');
        $score = 0.1;
        $this->post('/contact', $this->valid(['g-recaptcha-response' => 'contact:x']))->assertSessionHasErrors('g-recaptcha-response');
        $this->assertSame(0, ContactMessage::count());

        $score = 0.9;
        $this->post('/contact', $this->valid(['g-recaptcha-response' => 'contact:x']))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, ContactMessage::count());
    }

    public function test_form_is_rate_limited(): void
    {
        $this->signIn();
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

    public function test_admin_replies_with_text_and_image_from_panel_address(): void
    {
        Mail::fake();
        Storage::fake('public');
        Setting::put(Setting::CONTACT, ['email' => 'hi@x.test', 'form_recipient' => 'help@x.test']);
        $admin = User::factory()->admin()->create(['name' => 'Niket']);
        $m = ContactMessage::create($this->valid());

        Livewire::actingAs($admin)->test(ViewContactMessage::class, ['record' => $m->getKey()])
            ->callAction('reply', data: [
                'body' => "Add the APP_URL line.\nThen restart nginx.",
                'images' => [UploadedFile::fake()->image('fix.png', 400, 300)],
            ])
            ->assertHasNoActionErrors();

        $reply = ContactMessageReply::sole();
        $this->assertNotNull($reply->sent_at);
        $this->assertSame($admin->id, $reply->user_id);
        $this->assertCount(1, $reply->images);
        Storage::disk('public')->assertExists($reply->images[0]);
        $this->assertSame('in_progress', $m->fresh()->status);

        Mail::assertSent(ContactReplyMail::class, function (ContactReplyMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('asha@example.com')
                && $mail->hasReplyTo('help@x.test')
                && $mail->hasSubject('Re: Console shows no styles #00001')
                && str_contains($html, 'Hi Asha Rao,')
                && str_contains($html, 'Add the APP_URL line.<br />')
                && str_contains($html, 'Reference: #00001');
        });

        $this->actingAs($admin)->get("/admin/messages/{$m->id}")->assertOk()
            ->assertSee('Replies')->assertSee('by Niket');
    }

    public function test_failed_reply_is_kept_with_error(): void
    {
        $admin = User::factory()->admin()->create();
        $m = ContactMessage::create($this->valid());
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);

        Livewire::actingAs($admin)->test(ViewContactMessage::class, ['record' => $m->getKey()])
            ->callAction('reply', data: ['body' => 'Hello'])
            ->assertNotified('Reply not sent');

        $reply = ContactMessageReply::sole();
        $this->assertNull($reply->sent_at);
        $this->assertNotEmpty($reply->error);
        $this->assertSame('new', $m->fresh()->status);
    }
}
