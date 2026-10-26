<?php

namespace App\Filament\Resources\DeviceBrands\Pages;

use App\Filament\Resources\DeviceBrands\DeviceBrandResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDeviceBrand extends EditRecord
{
    protected static string $resource = DeviceBrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
