<?php

namespace App\Filament\Resources\KnownIssues\Pages;

use App\Filament\Resources\KnownIssues\KnownIssueResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKnownIssue extends EditRecord
{
    protected static string $resource = KnownIssueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
