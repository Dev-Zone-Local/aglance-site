<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ContactMessageResource::replyAction(),
            Action::make('resolve')
                ->label('Mark as resolved')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn () => $this->record->status !== 'resolved')
                ->action(fn () => $this->record->update(['status' => 'resolved'])),
            ContactMessageResource::updateAction(),
            DeleteAction::make(),
        ];
    }
}
