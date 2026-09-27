<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\GithubOAuth;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

class GithubSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'GitHub login';

    protected static ?string $title = 'GitHub login';

    protected function settingKey(): string
    {
        return Setting::GITHUB;
    }

    public function mount(): void
    {
        $saved = Setting::get(Setting::GITHUB);

        // Never send the stored secret back to the browser.
        $this->form->fill([
            'enabled' => $saved['enabled'] ?? true,
            'client_id' => $saved['client_id'] ?? config('services.github.client_id'),
            'client_secret' => null,
            'redirect' => $saved['redirect'] ?? GithubOAuth::defaultRedirect(),
        ]);
    }

    protected function fields(): array
    {
        $hasSecret = filled(GithubOAuth::credentials()['client_secret']);

        return [
            Section::make('How to set up')
                ->columnSpanFull()
                ->schema([
                    Text::make('1. On GitHub go to Settings → Developer settings → OAuth Apps → New OAuth App.'),
                    Text::make('2. Homepage URL: '.config('atglance.frontend_url')),
                    Text::make('3. Authorization callback URL: the "Callback URL" below (default '.GithubOAuth::defaultRedirect().').'),
                    Text::make('4. Paste the Client ID and a generated Client secret here and save.'),
                    Text::make('Admins sign in with GitHub when their GitHub primary email matches their account email. Otherwise sign in with GitHub once, then give that user the Admin role under Users.'),
                ]),
            Toggle::make('enabled')
                ->label('Enable "Sign in with GitHub"')
                ->columnSpanFull(),
            TextInput::make('client_id')
                ->label('Client ID')
                ->required(fn (Get $get) => (bool) $get('enabled')),
            TextInput::make('client_secret')
                ->label('Client secret')
                ->password()
                ->revealable()
                ->placeholder($hasSecret ? '•••••••• (saved — leave empty to keep)' : null)
                ->required(fn (Get $get) => $get('enabled') && ! $hasSecret),
            TextInput::make('redirect')
                ->label('Callback URL')
                ->url()
                ->required()
                ->columnSpanFull()
                ->helperText('Must exactly match the Authorization callback URL of the GitHub OAuth app.'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $saved = Setting::get(Setting::GITHUB);

        Setting::put(Setting::GITHUB, [
            'enabled' => (bool) $data['enabled'],
            'client_id' => $data['client_id'] ?: null,
            'client_secret' => filled($data['client_secret'])
                ? GithubOAuth::encryptSecret($data['client_secret'])
                : ($saved['client_secret'] ?? null),
            'redirect' => $data['redirect'],
        ]);

        $this->form->fill([...$data, 'client_secret' => null]);

        Notification::make()->title('GitHub login settings saved')->success()->send();
    }
}
