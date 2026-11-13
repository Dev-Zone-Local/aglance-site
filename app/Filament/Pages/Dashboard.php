<?php

namespace App\Filament\Pages;

use App\Support\AdminStats;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

/**
 * Admin home: users (free / enterprise), licences in use, messages and the last week's activity.
 * One custom view (filament.dashboard.overview) with its own styles; numbers from AdminStats.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Overview';

    protected Width|string|null $maxContentWidth = Width::Full;

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.dashboard.overview')->viewData(['s' => AdminStats::all()]),
        ]);
    }
}
