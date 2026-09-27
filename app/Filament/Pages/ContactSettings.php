<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

class ContactSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Contact';

    protected static ?string $title = 'Contact settings';

    protected function settingKey(): string
    {
        return Setting::CONTACT;
    }

    protected function fields(): array
    {
        return [
            TextInput::make('email')->email()->required(),
            TextInput::make('sales_email')->email()->required(),
            TextInput::make('support_email')->email()->required(),
            TextInput::make('address')->required(),
            TextInput::make('github')->label('GitHub URL')->required(),
            TextInput::make('twitter')->label('Twitter URL')->required(),
        ];
    }
}
