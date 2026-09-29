<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Support\LicenseTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Management Console licences of one user, shown on the admin's user edit page.
 */
class LicensesRelationManager extends RelationManager
{
    protected static string $relationship = 'licenses';

    /** "Licences (1 awaiting approval)" so pending approvals stand out on the edit page. */
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        $pending = $ownerRecord->licenses()->awaitingApproval()->count();

        return $pending ? "Licences ({$pending} awaiting approval)" : 'Licences';
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return LicenseTable::configure($table);
    }
}
