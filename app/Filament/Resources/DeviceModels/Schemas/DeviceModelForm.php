<?php

namespace App\Filament\Resources\DeviceModels\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DeviceModelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('device_brand_id')
                    ->relationship('deviceBrand', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('release_year')
                    ->numeric()
                    ->default(null),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
