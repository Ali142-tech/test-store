<?php

namespace App\Filament\Resources\ShippingZones;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\ShippingZones\Pages\CreateShippingZone;
use App\Filament\Resources\ShippingZones\Pages\EditShippingZone;
use App\Filament\Resources\ShippingZones\Pages\ListShippingZones;
use App\Models\ShippingZone;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShippingZoneResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'manage shipping';

    protected static ?string $readPermission = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $model = ShippingZone::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TagsInput::make('cities')->helperText('Cities served by this zone. Leave empty to serve every city.'),
            Toggle::make('is_active')->default(true),
            Repeater::make('methods')->relationship()->columnSpanFull()->columns(4)->schema([
                TextInput::make('name')->required(),
                Select::make('audience')->options(['all' => 'Everyone', 'retail' => 'Retail', 'wholesale' => 'Wholesale'])->default('all')->required(),
                TextInput::make('base_rate')->numeric()->minValue(0)->required()->default(0),
                TextInput::make('per_kg_rate')->numeric()->minValue(0)->default(0)->helperText('Added per started kg'),
                TextInput::make('free_over')->numeric()->minValue(0)->helperText('Free shipping at this subtotal'),
                TextInput::make('min_days')->numeric()->minValue(0), TextInput::make('max_days')->numeric()->minValue(0),
                Toggle::make('is_active')->default(true),
            ]),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(), TextColumn::make('cities')->badge()->limitList(3)->placeholder('All cities'),
                TextColumn::make('methods_count')->counts('methods')->label('Methods'), IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListShippingZones::route('/'), 'create' => CreateShippingZone::route('/create'), 'edit' => EditShippingZone::route('/{record}/edit')];
    }
}
