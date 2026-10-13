<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\SsoProvider;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Settings page for one "Sign in with ..." provider: on/off, client ID, client secret
 * (stored encrypted, never sent back to the browser) and callback URL.
 */
abstract class SsoSettingsPage extends SettingsPage
{
    /** @return class-string<SsoProvider> */
    abstract protected function provider(): string;

    /** Provider name for labels, e.g. "GitHub". */
    abstract protected function providerName(): string;

    /** @return list<string> numbered setup steps shown at the top of the page */
    abstract protected function setupSteps(): array;

    public function mount(): void
    {
        $saved = Setting::get($this->settingKey());

        $this->form->fill([
            'enabled' => $saved['enabled'] ?? false,
            'client_id' => $saved['client_id'] ?? null,
            'client_secret' => null,
            'redirect' => $saved['redirect'] ?? $this->provider()::defaultRedirect(),
        ]);
    }

    protected function fields(): array
    {
        $hasSecret = filled($this->provider()::credentials()['client_secret']);

        return [
            Section::make('How to set up')
                ->columnSpanFull()
                ->schema(array_map(fn (string $step) => Text::make($step), $this->setupSteps())),
            Toggle::make('enabled')
                ->label('Enable "Sign in with '.$this->providerName().'"')
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
                ->helperText('Must exactly match the redirect / callback URL registered with '.$this->providerName().'.'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $saved = Setting::get($this->settingKey());

        Setting::put($this->settingKey(), [
            'enabled' => (bool) $data['enabled'],
            'client_id' => $data['client_id'] ?: null,
            'client_secret' => filled($data['client_secret'])
                ? $this->provider()::encryptSecret($data['client_secret'])
                : ($saved['client_secret'] ?? null),
            'redirect' => $data['redirect'],
        ]);

        $this->form->fill([...$data, 'client_secret' => null]);

        Notification::make()->title($this->providerName().' login settings saved')->success()->send();
    }
}
