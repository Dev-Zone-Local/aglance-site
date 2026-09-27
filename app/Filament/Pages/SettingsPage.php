<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Field;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

/**
 * Base page that edits one row of the key/value `settings` table.
 */
abstract class SettingsPage extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    /** The `settings.key` this page edits. */
    abstract protected function settingKey(): string;

    /** @return array<Component|Field> */
    abstract protected function fields(): array;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(Setting::get($this->settingKey()));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->fields())
            ->columns(2)
            ->statePath('data');
    }

    public function save(): void
    {
        Setting::put($this->settingKey(), $this->form->getState());

        Notification::make()->title('Saved')->success()->send();
    }
}
