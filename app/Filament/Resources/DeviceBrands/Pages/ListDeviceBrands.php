<?php

namespace App\Filament\Resources\DeviceBrands\Pages;

use App\Filament\Resources\DeviceBrands\DeviceBrandResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDeviceBrands extends ListRecords
{
    protected static string $resource = DeviceBrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
