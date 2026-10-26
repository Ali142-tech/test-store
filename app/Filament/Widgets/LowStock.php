<?php

namespace App\Filament\Widgets;

use App\Models\ProductVariant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LowStock extends TableWidget
{
    protected static ?int $sort = 5;

    protected static ?string $heading = 'Low stock';

    public static function canView(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->can('manage inventory') || $u?->can('view reports'));
    }

    public function table(Table $table): Table
    {
        return $table->query(
            ProductVariant::query()->with('product')->where('is_active', true)
                ->whereHas('product', fn ($q) => $q->where('type', 'simple')->where('status', 'active'))
                ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->orderBy('stock_quantity')->limit(10)
        )->paginated(false)->columns([
            TextColumn::make('sku'), TextColumn::make('product.name')->limit(30),
            TextColumn::make('stock_quantity')->label('Stock')->badge()->color('danger'), TextColumn::make('low_stock_threshold')->label('Threshold'),
        ]);
    }
}
