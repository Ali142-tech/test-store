<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopProducts extends TableWidget
{
    protected static ?int $sort = 6;

    protected static ?string $heading = 'Top sellers (30 days, excluding cancelled)';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('view reports');
    }

    public function table(Table $table): Table
    {
        $query = OrderItem::query()
            ->selectRaw('MIN(order_items.id) as id, order_items.product_name, SUM(order_items.quantity - order_items.refunded_quantity) as units, SUM(order_items.line_total) as revenue')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')->where('orders.placed_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('order_items.product_name')->orderByDesc('units')->limit(10);

        return $table->query($query)->paginated(false)->defaultKeySort(false)->columns([
            TextColumn::make('product_name'), TextColumn::make('units'), TextColumn::make('revenue')->money('PKR'),
        ]);
    }
}
