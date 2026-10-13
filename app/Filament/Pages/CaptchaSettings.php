<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Captcha;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/** Google reCAPTCHA v3 on website sign-in / sign-up, the contact form and the admin login (App\Support\Captcha). */
class CaptchaSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'CAPTCHA';

    protected static ?string $title = 'CAPTCHA';

    protected function settingKey(): string
    {
        return Setting::CAPTCHA;
    }

    public function mount(): void
    {
        $saved = Setting::get(Setting::CAPTCHA);

        // Never send the stored secret back to the browser.
        $this->form->fill([
            'enabled' => $saved['enabled'] ?? false,
            'site_key' => $saved['site_key'] ?? null,
            'secret_key' => null,
            'min_score' => $saved['min_score'] ?? Captcha::DEFAULT_MIN_SCORE,
        ]);
    }

    protected function fields(): array
    {
        $hasSecret = filled(Captcha::secretKey());
        $host = parse_url((string) config('atglance.frontend_url'), PHP_URL_HOST) ?: 'your domain';

        return [
            Section::make('How to set up')
                ->columnSpanFull()
                ->schema([
                    Text::make('1. Open https://www.google.com/recaptcha/admin and create a key. Choose reCAPTCHA v3 (score based) — a v2 key gives "Invalid key type".'),
                    Text::make("2. Domains: add {$host} (and localhost if you test locally)."),
                    Text::make('3. Paste the site key and secret key here, turn it on and save.'),
                    Text::make('It protects the website sign-in, sign-up and contact forms and this admin login. Visitors see no puzzle; Google scores each request from 0.0 (bot) to 1.0 (human).'),
                    Text::make('Locked out because of a wrong key? Run `php artisan atglance:captcha --off` on the server.'),
                ]),
            Toggle::make('enabled')
                ->label('Enable CAPTCHA')
                ->columnSpanFull(),
            TextInput::make('site_key')
                ->label('Site key')
                ->required(fn (Get $get) => (bool) $get('enabled')),
            TextInput::make('secret_key')
                ->label('Secret key')
                ->password()
                ->revealable()
                ->placeholder($hasSecret ? '•••••••• (saved — leave empty to keep)' : null)
                ->required(fn (Get $get) => $get('enabled') && ! $hasSecret),
            TextInput::make('min_score')
                ->label('Minimum score')
                ->numeric()
                ->minValue(0)
                ->maxValue(1)
                ->step(0.1)
                ->required()
                ->helperText('Sign-ins scoring below this are refused. 0.5 is Google\'s suggestion; lower it if real people are blocked.'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Captcha::save((bool) $data['enabled'], $data['site_key'] ?? null, $data['secret_key'] ?? null, (float) $data['min_score']);

        $this->form->fill([...$data, 'secret_key' => null]);

        Notification::make()->title('CAPTCHA settings saved')->success()->send();
    }
}
