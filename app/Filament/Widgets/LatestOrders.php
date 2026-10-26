<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestOrders extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent orders';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('view orders');
    }

    public function table(Table $table): Table
    {
        return $table->query(Order::query()->latest('placed_at')->limit(8))->paginated(false)
            ->columns([
                TextColumn::make('reference')->url(fn ($record) => OrderResource::getUrl('view', ['record' => $record])),
                TextColumn::make('placed_at')->dateTime('d M H:i'), TextColumn::make('customer_type')->badge(),
                TextColumn::make('status')->badge(), TextColumn::make('payment_status')->badge(), TextColumn::make('grand_total')->money('PKR'),
            ]);
    }
}
