<?php

namespace App\Filament\Resources\KnownIssues;

use App\Filament\Resources\KnownIssues\Pages\CreateKnownIssue;
use App\Filament\Resources\KnownIssues\Pages\EditKnownIssue;
use App\Filament\Resources\KnownIssues\Pages\ListKnownIssues;
use App\Filament\Resources\KnownIssues\Schemas\KnownIssueForm;
use App\Filament\Resources\KnownIssues\Tables\KnownIssuesTable;
use App\Models\KnownIssue;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/** Known problems and solutions, shown on /known-problems (the "Quick fixes" buttons in the install guide). */
class KnownIssueResource extends Resource
{
    protected static ?string $model = KnownIssue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Known problems';

    protected static ?string $modelLabel = 'known problem';

    protected static ?string $slug = 'known-problems';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return KnownIssueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KnownIssuesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKnownIssues::route('/'),
            'create' => CreateKnownIssue::route('/create'),
            'edit' => EditKnownIssue::route('/{record}/edit'),
        ];
    }
}
