<?php

namespace App\Filament\Resources\LicenseFiles;

use App\Filament\Resources\LicenseFiles\Pages\ListLicenseFiles;
use App\Models\LicenseFile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                TextColumn::make('licence.name')->label('Licence')->fontFamily('mono')
                    ->description(fn (LicenseFile $r) => $r->issued_by === 'console' ? 'online activation' : 'download'),
                TextColumn::make('org')->label('Customer')->searchable()->toggleable(),
                TextColumn::make('plan')->badge()->color('gray'),
                TextColumn::make('policy_type')->label('Policy')->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'custom' ? 'Custom' : 'Standard')
                    ->color(fn (?string $state) => $state === 'custom' ? 'info' : 'gray')
                    ->description(fn (LicenseFile $r) => $r->policy_id),
                TextColumn::make('max_nodes')->label('Nodes')
                    ->state(fn (LicenseFile $r) => $r->max_nodes === null ? '—' : ($r->max_nodes === 0 ? 'Unlimited' : $r->max_nodes)),
                TextColumn::make('max_users')->label('Users')
                    ->state(fn (LicenseFile $r) => $r->max_users ?? $r->seats)
                    ->formatStateUsing(fn ($state) => $state === 0 ? 'Unlimited' : $state),
                TextColumn::make('features')->label('Features')->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('issued_by')->label('Issued by')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('issued_at')->label('Issued')->dateTime()->sortable(),
                TextColumn::make('expires_at')->label('Expires')->date()->sortable()
                    ->color(fn (LicenseFile $r) => $r->isExpired() ? 'danger' : null),
                TextColumn::make('install_id')->label('Installation')->fontFamily('mono')->placeholder('Any')->toggleable(),
                TextColumn::make('key_id')->label('Key ID')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user')->relationship('user', 'email')->searchable()->preload(),
                SelectFilter::make('policy_type')->label('Policy')->options(['default' => 'Standard', 'custom' => 'Custom']),
                SelectFilter::make('plan')->options(collect(config('atglance.plans'))->mapWithKeys(fn ($p, $code) => [$code => $p['label']])->all()),
                TernaryFilter::make('expired')
                    ->label('Expiry')
                    ->trueLabel('Expired')
                    ->falseLabel('Valid')
                    ->queries(
                        true: fn (Builder $q) => $q->where('expires_at', '<=', now()),
                        false: fn (Builder $q) => $q->where('expires_at', '>', now()),
                    ),
            ]);
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
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
