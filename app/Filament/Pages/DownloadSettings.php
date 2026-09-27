<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

class DownloadSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $navigationLabel = 'Downloads';

    protected static ?string $title = 'Download settings';

    protected function settingKey(): string
    {
        return Setting::DOWNLOADS;
    }

    protected function fields(): array
    {
        return [
            TextInput::make('cli_url')->label('CLI URL')->required(),
            TextInput::make('cli_version')->label('CLI version')->required(),
            TextInput::make('cli_checksum')->label('CLI checksum'),
            TextInput::make('console_url')->label('Console URL')->required(),
            TextInput::make('console_version')->label('Console version')->required(),
            TextInput::make('console_checksum')->label('Console checksum'),
            TextInput::make('cli_install_command')->label('CLI install command')->required()->columnSpanFull(),
        ];
    }
}
