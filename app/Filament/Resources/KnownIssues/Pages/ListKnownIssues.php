<?php

namespace App\Filament\Resources\KnownIssues\Pages;

use App\Filament\Resources\KnownIssues\KnownIssueResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListKnownIssues extends ListRecords
{
    protected static string $resource = KnownIssueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('page')
                ->label('Open public page')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(route('known-problems'), shouldOpenInNewTab: true),
            CreateAction::make()->label('Add known problem'),
        ];
    }
}
