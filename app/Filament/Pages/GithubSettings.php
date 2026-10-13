<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\GithubOAuth;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class GithubSettings extends SsoSettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'GitHub login';

    protected static ?string $title = 'GitHub login';

    protected function settingKey(): string
    {
        return Setting::GITHUB;
    }

    protected function provider(): string
    {
        return GithubOAuth::class;
    }

    protected function providerName(): string
    {
        return 'GitHub';
    }

    protected function setupSteps(): array
    {
        return [
            '1. On GitHub go to Settings → Developer settings → OAuth Apps → New OAuth App.',
            '2. Homepage URL: '.config('atglance.frontend_url'),
            '3. Authorization callback URL: the "Callback URL" below (default '.GithubOAuth::defaultRedirect().').',
            '4. Paste the Client ID and a generated Client secret here, turn it on and save.',
            'The button shows on the website sign-in and sign-up pages. Admins sign in with GitHub when their GitHub primary email matches their account email.',
        ];
    }
}
