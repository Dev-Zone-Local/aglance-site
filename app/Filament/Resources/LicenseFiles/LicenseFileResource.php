<?php

namespace App\Filament\Resources\LicenseFiles;

use App\Filament\Resources\LicenseFiles\Pages\ListLicenseFiles;
use App\Models\LicenseFile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Offline licence files (.atgl) issued to users. Read-only: a file cannot be revoked once
 * downloaded (the console checks it offline); it stops working at expires_at.
 */
class LicenseFileResource extends Resource
{
    protected static ?string $model = LicenseFile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $navigationLabel = 'Licence files';

    protected static ?string $modelLabel = 'licence file';

    protected static string|\UnitEnum|null $navigationGroup = 'Access';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'licence-files';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('license_id')->label('Licence ID')->searchable()->fontFamily('mono')->copyable(),
                TextColumn::make('user.email')->label('User')->searchable(),
                TextColumn::make('org')->label('Customer')->searchable()->toggleable(),
                TextColumn::make('plan')->badge()->color('gray'),
                TextColumn::make('seats')->numeric()->toggleable(),
                TextColumn::make('issued_at')->label('Issued')->dateTime()->sortable(),
                TextColumn::make('expires_at')->label('Expires')->date()->sortable()
                    ->color(fn (LicenseFile $r) => $r->isExpired() ? 'danger' : null),
                TextColumn::make('install_id')->label('Installation')->fontFamily('mono')->placeholder('Any')->toggleable(),
                TextColumn::make('key_id')->label('Key ID')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('plan')->options(collect(config('atglance.plans'))->mapWithKeys(fn ($p, $code) => [$code => $p['label']])->all()),
            ]);
    }

    public static function canCreate(): bool
    {
        return false; // users generate files from their dashboard
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLicenseFiles::route('/'),
        ];
    }
}
