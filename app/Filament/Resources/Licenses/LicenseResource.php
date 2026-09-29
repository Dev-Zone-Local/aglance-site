<?php

namespace App\Filament\Resources\Licenses;

use App\Filament\Resources\Licenses\Pages\ListLicenses;
use App\Filament\Support\LicenseTable;
use App\Models\License;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * All Management Console licences across users. Approval is only needed for users flagged
 * with "Licences need admin approval"; admins approve or decline those here.
 */
class LicenseResource extends Resource
{
    protected static ?string $model = License::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'Licences';

    protected static ?string $modelLabel = 'licence';

    protected static ?string $pluralModelLabel = 'licences';

    protected static string|\UnitEnum|null $navigationGroup = 'Access';

    protected static ?int $navigationSort = 0;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->licenses();
    }

    public static function table(Table $table): Table
    {
        return LicenseTable::configure($table, withOwner: true);
    }

    public static function canCreate(): bool
    {
        return false; // users request licences from their dashboard
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = License::licenses()->awaitingApproval()->count();

        return $pending ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Licences awaiting admin approval';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLicenses::route('/'),
        ];
    }
}
