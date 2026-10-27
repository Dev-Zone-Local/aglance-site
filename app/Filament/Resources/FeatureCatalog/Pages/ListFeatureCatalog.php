<?php

namespace App\Filament\Resources\FeatureCatalog\Pages;

use App\Filament\Resources\FeatureCatalog\FeatureCatalogResource;
use App\Support\LicensePolicy\FeatureCatalogSync;
use Filament\Resources\Pages\ListRecords;

class ListFeatureCatalog extends ListRecords
{
    protected static string $resource = FeatureCatalogResource::class;

    public function mount(): void
    {
        // Keys added to the contract since the last deploy appear without a migration.
        FeatureCatalogSync::sync();
        parent::mount();
    }
}
