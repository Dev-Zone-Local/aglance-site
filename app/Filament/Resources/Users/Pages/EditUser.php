<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\CustomLicenseActions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Create / edit / revoke the user's custom license (Users → Edit).
            CustomLicenseActions::edit(),
            CustomLicenseActions::revoke(),
            DeleteAction::make(),
        ];
    }
}
