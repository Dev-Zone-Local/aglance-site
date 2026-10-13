<?php

namespace App\Filament\Resources\Licenses\Pages;

use App\Filament\Resources\Licenses\LicenseResource;
use App\Models\License;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListLicenses extends ListRecords
{
    protected static string $resource = LicenseResource::class;

    public function getTabs(): array
    {
        $awaiting = License::licenses()->awaitingApproval()->count();

        return [
            'all' => Tab::make('All'),
            'awaiting' => Tab::make('Awaiting approval')
                ->badge($awaiting ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->awaitingApproval()),
            'unverified' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('confirmed_at')),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('confirmed_at')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return License::licenses()->awaitingApproval()->exists() ? 'awaiting' : 'all';
    }
}
