<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;

    public function getSubheading(): ?string
    {
        return 'Messages from the contact form on /contact. Each one is also emailed to '.ContactMessage::recipient().'.';
    }

    public function getTabs(): array
    {
        $tabs = ['open' => Tab::make('Open')
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['new', 'in_progress']))
            ->badge(ContactMessage::whereIn('status', ['new', 'in_progress'])->count() ?: null)];
        foreach (ContactMessage::STATUSES as $key => $label) {
            $tabs[$key] = Tab::make($label)->modifyQueryUsing(fn (Builder $query) => $query->where('status', $key));
        }

        return $tabs + ['all' => Tab::make('All')];
    }
}
