<?php

namespace App\Filament\Resources\Shipments;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Shipments\Pages\ListShipments;
use App\Models\Shipment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Shipments are created from the order page; here staff update courier/tracking and delivery status. */
class ShipmentResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'manage shipping';

    protected static ?string $readPermission = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $model = Shipment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.reference')->searchable(), TextColumn::make('courier')->searchable(), TextColumn::make('tracking_number')->searchable()->copyable(),
                TextColumn::make('status')->badge(), TextColumn::make('shipped_at')->dateTime('d M Y')->sortable(), TextColumn::make('delivered_at')->dateTime('d M Y'),
            ])
            ->defaultSort('shipped_at', 'desc')
            ->filters([SelectFilter::make('status')->options(['pending' => 'Pending', 'in_transit' => 'In transit', 'delivered' => 'Delivered', 'returned' => 'Returned'])])
            ->recordActions([
                Action::make('update')->label('Update')->icon('heroicon-o-pencil')
                    ->fillForm(fn (Shipment $r) => $r->only(['courier', 'tracking_number', 'tracking_url', 'status']))
                    ->schema([
                        TextInput::make('courier')->required(), TextInput::make('tracking_number')->required(), TextInput::make('tracking_url')->url(),
                        Select::make('status')->options(['pending' => 'Pending', 'in_transit' => 'In transit', 'delivered' => 'Delivered', 'returned' => 'Returned'])->required(),
                    ])
                    ->action(function (Shipment $r, array $data) {
                        abort_unless(auth()->user()->can('manage shipping'), 403);
                        $r->update($data + ['delivered_at' => $data['status'] === 'delivered' ? ($r->delivered_at ?? now()) : null]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListShipments::route('/')];
    }
}
