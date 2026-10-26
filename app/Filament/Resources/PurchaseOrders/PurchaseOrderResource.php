<?php

namespace App\Filament\Resources\PurchaseOrders;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Services\Inventory\PurchaseReceivingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PurchaseOrderResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'manage suppliers';

    protected static ?string $readPermission = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $recordTitleAttribute = 'reference';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function canEdit(Model $record): bool
    {
        return static::allowed(static::$permission) && in_array($record->status, ['draft', 'ordered'], true);
    }

    public static function canDelete(Model $record): bool
    {
        return $record->status === 'draft' && parent::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('reference')->default(fn () => 'PO-'.now()->format('ymd').'-'.Str::upper(Str::random(4)))->required()->unique(ignoreRecord: true)->disabledOn('edit'),
            Select::make('supplier_id')->relationship('supplier', 'name')->required()->searchable()->preload(),
            Select::make('status')->options(['draft' => 'Draft', 'ordered' => 'Ordered', 'cancelled' => 'Cancelled'])->default('draft')->required()
                ->helperText('Receiving stock is done with the "Receive stock" action.'),
            Textarea::make('notes'),
            Repeater::make('items')->relationship()->columnSpanFull()->columns(3)->schema([
                Select::make('product_variant_id')->label('Variant')->required()->searchable()
                    ->options(fn () => ProductVariant::with('product')->get()->mapWithKeys(fn ($v) => [$v->id => $v->sku.' - '.$v->displayName()])),
                TextInput::make('quantity')->numeric()->minValue(1)->required(),
                TextInput::make('unit_cost')->numeric()->minValue(0)->required(),
            ]),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->searchable(), TextColumn::make('supplier.name')->searchable(),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) { 'received' => 'success', 'partial' => 'info', 'cancelled' => 'danger', default => 'warning' }),
                TextColumn::make('total_cost')->money('PKR'), TextColumn::make('created_at')->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()->visible(fn ($record) => in_array($record->status, ['draft', 'ordered'], true)),
                Action::make('receive')->label('Receive stock')->icon('heroicon-o-inbox-arrow-down')->color('success')->requiresConfirmation()
                    ->modalDescription('Adds the outstanding quantity of every line to stock and records stock movements.')
                    ->visible(fn ($record) => in_array($record->status, ['draft', 'ordered', 'partial'], true) && auth()->user()->can('manage inventory'))
                    ->action(function (PurchaseOrder $r) {
                        abort_unless(auth()->user()->can('manage inventory'), 403);
                        app(PurchaseReceivingService::class)->receiveAll($r, auth()->user());
                        Notification::make()->title('Stock received')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
