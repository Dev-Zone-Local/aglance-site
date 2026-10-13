<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\GoogleOAuth;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class GoogleSettings extends SsoSettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAtSymbol;

    protected static ?string $navigationLabel = 'Google login';

    protected static ?string $title = 'Google login';

    protected function settingKey(): string
    {
        return Setting::GOOGLE;
    }

    protected function provider(): string
    {
        return GoogleOAuth::class;
    }

    protected function providerName(): string
    {
        return 'Google';
    }

    protected function setupSteps(): array
    {
        return [
            '1. In Google Cloud Console (console.cloud.google.com) open APIs & Services → OAuth consent screen and set it up (External, app name, support email).',
            '2. Go to Credentials → Create credentials → OAuth client ID → Web application.',
            '3. Authorized JavaScript origin: '.config('atglance.frontend_url'),
            '4. Authorized redirect URI: the "Callback URL" below (default '.GoogleOAuth::defaultRedirect().').',
            '5. Paste the Client ID and Client secret here, turn it on and save.',
            'The button shows on the website sign-in and sign-up pages. Accounts are linked only when Google says the email is verified. Google sign-in never makes anyone an admin.',
        ];
    }
}
