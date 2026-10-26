<?php

namespace App\Filament\Resources\ReturnRequests;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\ReturnRequests\Pages\ListReturnRequests;
use App\Models\ReturnRequest;
use App\Services\Orders\OrderService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ReturnRequestResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'manage orders';

    protected static ?string $readPermission = 'handle support';

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $model = ReturnRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

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

    public static function getNavigationBadge(): ?string
    {
        return (string) ReturnRequest::where('status', 'requested')->count() ?: null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime('d M Y')->sortable(), TextColumn::make('order.reference')->searchable(),
                TextColumn::make('item.product_name')->label('Item'), TextColumn::make('quantity'), TextColumn::make('reason')->limit(50)->wrap(),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) { 'requested' => 'warning', 'approved' => 'info', 'rejected' => 'danger', default => 'success' }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([SelectFilter::make('status')->options(['requested' => 'Requested', 'approved' => 'Approved', 'rejected' => 'Rejected', 'refunded' => 'Refunded'])])
            ->recordActions([
                Action::make('approve')->color('info')->visible(fn ($record) => $record->status === 'requested' && auth()->user()->can('manage orders'))
                    ->action(fn (ReturnRequest $r) => $r->update(['status' => 'approved'])),
                Action::make('reject')->color('danger')->visible(fn ($record) => $record->status === 'requested' && auth()->user()->can('manage orders'))
                    ->schema([Textarea::make('admin_notes')->required()])
                    ->action(fn (ReturnRequest $r, array $data) => $r->update(['status' => 'rejected', 'admin_notes' => $data['admin_notes']])),
                // Goods received: put stock back and refund the line value (net of its share of any discount).
                Action::make('received')->label('Received & refund')->color('success')->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'approved' && auth()->user()->can('process refunds'))
                    ->action(function (ReturnRequest $r) {
                        abort_unless(auth()->user()->can('process refunds'), 403);
                        try {
                            $item = $r->item;
                            $order = $r->order;
                            $amount = bcdiv(bcmul((string) $item->line_total, (string) $r->quantity, 4), (string) $item->quantity, 2);
                            app(OrderService::class)->refund($order, $amount, "Return #{$r->id}: {$r->reason}", auth()->user(), [$item->id => $r->quantity]);
                            $r->update(['status' => 'refunded']);
                            Notification::make()->title("Refund of {$amount} recorded and stock restored")->success()->send();
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListReturnRequests::route('/')];
    }
}
