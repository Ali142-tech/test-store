<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Services\Inventory\InsufficientStockException;
use App\Services\Inventory\StockService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    private static function canPrice(): bool
    {
        return (bool) auth()->user()?->can('change prices');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->helperText('e.g. "Black / 1m". Leave empty for single-variant products.'),
            TextInput::make('sku')->required()->unique(ignoreRecord: true),
            TextInput::make('barcode'), TextInput::make('color'),
            KeyValue::make('attributes')->columnSpanFull()->keyLabel('Attribute')->valueLabel('Value'),

            // Price fields are only editable with the "change prices" permission (disabled fields are never saved).
            TextInput::make('price')->numeric()->required()->minValue(0)->disabled(fn () => ! self::canPrice()),
            TextInput::make('sale_price')->numeric()->minValue(0)->lt('price')->disabled(fn () => ! self::canPrice()),
            DateTimePicker::make('sale_starts_at')->disabled(fn () => ! self::canPrice()),
            DateTimePicker::make('sale_ends_at')->disabled(fn () => ! self::canPrice()),
            TextInput::make('cost_price')->numeric()->minValue(0)->visible(fn () => (bool) auth()->user()?->can('view cost prices'))->disabled(fn () => ! self::canPrice()),

            TextInput::make('initial_stock')->numeric()->minValue(0)->default(0)->visibleOn('create')->dehydrated(true)
                ->helperText('Recorded as an "initial stock" movement. Afterwards use the Adjust stock action.'),
            TextInput::make('low_stock_threshold')->numeric()->minValue(0)->default(5),
            TextInput::make('wholesale_moq')->numeric()->minValue(1)->default(1)->label('Wholesale minimum order qty'),
            Toggle::make('is_default'), Toggle::make('is_active')->default(true),

            Repeater::make('tiers')->relationship()->columnSpanFull()->label('Wholesale price tiers')->columns(3)
                ->visible(fn () => self::canPrice())
                ->schema([
                    TextInput::make('min_quantity')->numeric()->minValue(1)->required(),
                    TextInput::make('max_quantity')->numeric()->helperText('Empty = no upper limit'),
                    TextInput::make('price')->numeric()->required()->minValue(0),
                ]),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table->recordTitleAttribute('sku')
            ->columns([
                TextColumn::make('sku')->searchable(), TextColumn::make('name'),
                TextColumn::make('price')->money('PKR'), TextColumn::make('sale_price')->money('PKR')->placeholder('-'),
                TextColumn::make('cost_price')->money('PKR')->visible(fn () => (bool) auth()->user()?->can('view cost prices')),
                TextColumn::make('stock_quantity')->label('Stock')->badge()->color(fn ($record) => $record->stock_quantity <= $record->low_stock_threshold ? 'danger' : 'success'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->using(function (array $data, string $model) {
                    $initial = (int) ($data['initial_stock'] ?? 0);
                    unset($data['initial_stock']);
                    $variant = $this->getOwnerRecord()->variants()->create($data + ['stock_quantity' => 0]);
                    if ($initial > 0) {
                        app(StockService::class)->add($variant->id, $initial, 'adjustment', null, auth()->id(), 'Initial stock');
                    }

                    return $variant;
                }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('adjustStock')->label('Adjust stock')->icon('heroicon-o-adjustments-horizontal')
                    ->visible(fn () => (bool) auth()->user()?->can('manage inventory'))
                    ->schema([
                        TextInput::make('delta')->numeric()->required()->helperText('Positive to add, negative to remove.'),
                        Select::make('reason')->options(['adjustment' => 'Stock count correction', 'damage' => 'Damaged / lost', 'purchase' => 'Received from supplier', 'return' => 'Customer return', 'correction' => 'Other correction'])->required(),
                        Textarea::make('note'),
                    ])
                    ->action(function (array $data, $record) {
                        abort_unless(auth()->user()?->can('manage inventory'), 403);
                        try {
                            app(StockService::class)->adjust($record->id, (int) $data['delta'], $data['reason'], auth()->id(), $data['note'] ?? null);
                            Notification::make()->title('Stock updated')->success()->send();
                        } catch (InsufficientStockException) {
                            Notification::make()->title('Stock cannot go below zero')->danger()->send();
                        }
                    }),
                DeleteAction::make(),
            ]);
    }
}
