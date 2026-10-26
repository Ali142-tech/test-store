<?php

namespace App\Filament\Resources\DeviceBrands;

use App\Filament\Resources\DeviceBrands\Pages\CreateDeviceBrand;
use App\Filament\Resources\DeviceBrands\Pages\EditDeviceBrand;
use App\Filament\Resources\DeviceBrands\Pages\ListDeviceBrands;
use App\Filament\Resources\DeviceBrands\Schemas\DeviceBrandForm;
use App\Filament\Resources\DeviceBrands\Tables\DeviceBrandsTable;
use App\Models\DeviceBrand;
use App\Filament\Concerns\RequiresPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DeviceBrandResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'manage catalog';

    protected static ?string $readPermission = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $model = DeviceBrand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return DeviceBrandForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeviceBrandsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeviceBrands::route('/'),
            'create' => CreateDeviceBrand::route('/create'),
            'edit' => EditDeviceBrand::route('/{record}/edit'),
        ];
    }
}
