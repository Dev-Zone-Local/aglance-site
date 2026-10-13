<?php

namespace Tests\Feature;

use App\Filament\Pages\MailSettings as MailSettingsPage;
use App\Models\Setting;
use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_requires_admin(): void
    {
        $this->actingAs(User::factory()->create())
            ->withHeaders(['Accept' => 'text/html'])
            ->get('/admin/mail-settings')
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->withHeaders(['Accept' => 'text/html'])
            ->get('/admin/mail-settings')
            ->assertOk()
            ->assertSee('Send test email');
    }

    public function test_admin_saves_smtp_settings_with_encrypted_password(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(MailSettingsPage::class)
            ->set('data.enabled', true)
            ->set('data.host', 'smtp.example.com')
            ->set('data.port', 465)
            ->set('data.encryption', 'ssl')
            ->set('data.username', 'info@example.com')
            ->set('data.password', 'smtp-secret')
            ->set('data.from_address', 'info@example.com')
            ->set('data.from_name', 'AtGlance')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('data.password', null);

        $saved = Setting::get(Setting::MAIL);
        $this->assertNotSame('smtp-secret', $saved['password']);
        $this->assertSame('smtp-secret', MailSettings::password($saved));

        MailSettings::apply();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('smtp-secret', config('mail.mailers.smtp.password'));
        $this->assertSame('info@example.com', config('mail.from.address'));

        // Saving again with an empty password keeps the stored one.
        Livewire::test(MailSettingsPage::class)->set('data.host', 'smtp2.example.com')->call('save')->assertHasNoErrors();
        $this->assertSame('smtp-secret', MailSettings::password(Setting::get(Setting::MAIL)));
    }

    public function test_disabled_settings_leave_env_config_alone(): void
    {
        Setting::put(Setting::MAIL, ['enabled' => false, 'host' => 'smtp.example.com']);
        $before = config('mail.default');

        MailSettings::apply();

        $this->assertSame($before, config('mail.default'));
    }

    public function test_send_test_email_action(): void
    {
        config(['mail.default' => 'array']);
        $this->actingAs(User::factory()->admin()->create(['email' => 'boss@example.com']));

        Livewire::test(MailSettingsPage::class)
            ->callAction('sendTest', data: ['to' => 'ops@example.com'])
            ->assertHasNoActionErrors()
            ->assertNotified('Test email sent to ops@example.com');

        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $this->assertSame('ops@example.com', $sent->first()->getEnvelope()->getRecipients()[0]->getAddress());
    }
}
