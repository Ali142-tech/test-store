<?php

namespace App\Filament\Resources\StockMovements;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Append-only stock ledger. Stock is changed via StockService (orders, adjustments, purchase receipts) - never edited here. */
class StockMovementResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'manage inventory';

    protected static ?string $readPermission = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

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
                TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('variant.sku')->label('SKU')->searchable(),
                TextColumn::make('quantity_change')->badge()->color(fn ($state) => $state < 0 ? 'danger' : 'success')->sortable(),
                TextColumn::make('quantity_after')->label('Balance'),
                TextColumn::make('reason')->badge(),
                TextColumn::make('reference_type')->formatStateUsing(fn ($state, $record) => $state ? class_basename($state).' #'.$record->reference_id : '-'),
                TextColumn::make('user.name')->label('By')->placeholder('system'),
                TextColumn::make('note')->limit(40)->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([SelectFilter::make('reason')->options(['sale' => 'Sale', 'cancellation' => 'Cancellation', 'return' => 'Return', 'purchase' => 'Purchase', 'adjustment' => 'Adjustment', 'damage' => 'Damage', 'correction' => 'Correction'])]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStockMovements::route('/')];
    }
}
