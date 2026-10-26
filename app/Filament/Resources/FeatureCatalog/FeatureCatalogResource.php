<?php

namespace App\Filament\Resources\FeatureCatalog;

use App\Filament\Resources\FeatureCatalog\Pages\ListFeatureCatalog;
use App\Models\FeatureCatalog;
use App\Support\LicensePolicy\PolicyAudit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Feature catalog (under License Policy). The keys come from the shared contract (FEATURES.md)
 * and cannot be created, renamed or deleted here; admins edit the display name and description.
 */
class FeatureCatalogResource extends Resource
{
    protected static ?string $model = FeatureCatalog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|\UnitEnum|null $navigationGroup = 'Access';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Feature Catalog';

    protected static ?string $modelLabel = 'feature';

    protected static ?string $slug = 'feature-catalog';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->paginated(false)
            ->columns([
                TextColumn::make('key')->fontFamily('mono')->copyable(),
                TextColumn::make('name')->label('Display name'),
                TextColumn::make('tier')->badge()
                    ->color(fn (string $state) => $state === 'premium' ? 'warning' : 'gray')
                    ->tooltip(fn (string $state) => $state === 'premium' ? 'Enabled only when a licence lists it' : 'Always on in the console'),
                TextColumn::make('description')->wrap()->color('gray'),
                IconColumn::make('default_enabled')->label('On in new defaults')->boolean(),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->fillForm(fn (FeatureCatalog $record) => $record->only(['key', 'name', 'description']))
                    ->schema([
                        TextInput::make('key')->disabled()->dehydrated(false)->helperText('Keys come from the licence contract and never change.'),
                        TextInput::make('name')->label('Display name')->required()->maxLength(100),
                        Textarea::make('description')->rows(3)->maxLength(500),
                    ])
                    ->action(function (FeatureCatalog $record, array $data): void {
                        $old = $record->only(['name', 'description']);
                        $record->update(['name' => $data['name'], 'description' => $data['description'] ?? '']);
                        PolicyAudit::record(auth()->id(), 'feature_catalog', $record, 'Feature: '.$record->key, 'updated', $old, $record->only(['name', 'description']));
                        Notification::make()->title('Feature saved')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeatureCatalog::route('/'),
        ];
    }
}
