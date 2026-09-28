<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\MailSettings as Mail;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail as Mailer;
use Throwable;

class MailSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $navigationLabel = 'Email (SMTP)';

    protected static ?string $title = 'Email (SMTP) settings';

    protected function settingKey(): string
    {
        return Setting::MAIL;
    }

    public function mount(): void
    {
        $saved = Setting::get(Setting::MAIL);

        // Never send the stored password back to the browser.
        $this->form->fill([
            'enabled' => $saved['enabled'] ?? false,
            'host' => $saved['host'] ?? null,
            'port' => $saved['port'] ?? 587,
            'encryption' => $saved['encryption'] ?? 'tls',
            'username' => $saved['username'] ?? null,
            'password' => null,
            'from_address' => $saved['from_address'] ?? config('mail.from.address'),
            'from_name' => $saved['from_name'] ?? config('mail.from.name'),
        ]);
    }

    protected function fields(): array
    {
        $hasPassword = filled(Setting::get(Setting::MAIL)['password'] ?? null);
        $on = fn (Get $get) => (bool) $get('enabled');

        return [
            Toggle::make('enabled')
                ->label('Send email through this SMTP server')
                ->helperText('When off, the MAIL_* values from the server .env are used.')
                ->live()
                ->columnSpanFull(),
            TextInput::make('host')->label('SMTP host')->placeholder('smtp.example.com')->required($on),
            TextInput::make('port')->numeric()->required($on)->default(587),
            Select::make('encryption')->options(Mail::ENCRYPTION)->required($on)->default('tls'),
            TextInput::make('username')->label('SMTP username')->autocomplete(false),
            TextInput::make('password')
                ->label('SMTP password')
                ->password()
                ->revealable()
                ->autocomplete('new-password')
                ->placeholder($hasPassword ? '•••••••• (saved — leave empty to keep)' : null),
            TextInput::make('from_address')->label('From address')->email()->required($on),
            TextInput::make('from_name')->label('From name')->required($on),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $saved = Setting::get(Setting::MAIL);

        Setting::put(Setting::MAIL, [
            'enabled' => (bool) $data['enabled'],
            'host' => $data['host'] ?: null,
            'port' => $data['port'] ? (int) $data['port'] : null,
            'encryption' => $data['encryption'] ?? 'tls',
            'username' => $data['username'] ?: null,
            'password' => filled($data['password'])
                ? Crypt::encryptString($data['password'])
                : ($saved['password'] ?? null),
            'from_address' => $data['from_address'] ?: null,
            'from_name' => $data['from_name'] ?: null,
        ]);

        $this->form->fill([...$data, 'password' => null]);

        Notification::make()->title('Email settings saved')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label('Send test email')
                ->icon(Heroicon::OutlinedEnvelope)
                ->modalDescription('Uses the saved settings. Save your changes first.')
                ->schema([
                    TextInput::make('to')
                        ->label('Send to')
                        ->email()
                        ->required()
                        ->default(fn () => auth()->user()?->email),
                ])
                ->action(function (array $data): void {
                    try {
                        // Re-read the saved settings in case the mailer was built earlier in this request.
                        app('mail.manager')->forgetMailers();
                        Mail::apply();

                        Mailer::raw(
                            'This is a test email from the AtGlance admin panel. If you can read this, outgoing email works.',
                            fn ($m) => $m->to($data['to'])->subject('AtGlance test email')
                        );

                        Notification::make()->title("Test email sent to {$data['to']}")->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Test email failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
