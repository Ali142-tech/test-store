<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Audit\AuditLogger;
use App\Services\Orders\OrderService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use InvalidArgumentException;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    private function guard(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }

    private function run(callable $fn, string $success): void
    {
        try {
            $fn();
            $this->record->refresh();
            Notification::make()->title($success)->success()->send();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        /** @var Order $order */
        $order = $this->record;

        return [
            Action::make('changeStatus')->label('Change status')->icon('heroicon-o-arrow-path')
                ->visible(fn () => auth()->user()->can('manage orders') && Order::TRANSITIONS[$order->status] !== [])
                ->schema([
                    Select::make('status')->options(fn () => collect(Order::TRANSITIONS[$this->record->status])->mapWithKeys(fn ($state) => [$state => ucfirst($state)]))->required(),
                    Textarea::make('note'),
                ])
                ->action(function (array $data) {
                    $this->guard('manage orders');
                    $this->run(fn () => app(OrderService::class)->transition($this->record, $data['status'], $data['note'] ?? null, auth()->user()), 'Status updated');
                }),

            Action::make('confirmBank')->label('Verify bank transfer')->icon('heroicon-o-banknotes')->color('success')
                ->visible(fn () => auth()->user()->can('manage orders') && $order->payment_method === 'bank_transfer' && $order->payment_status === 'pending' && $order->status !== 'cancelled')
                ->schema([TextInput::make('reference')->label('Bank transaction reference')->required()])
                ->action(function (array $data) {
                    $this->guard('manage orders');
                    $this->run(fn () => app(OrderService::class)->confirmBankTransfer($this->record, $data['reference'], auth()->user()), 'Payment verified');
                }),

            Action::make('markPaid')->label('Mark COD as paid')->icon('heroicon-o-check-circle')
                ->visible(fn () => auth()->user()->can('manage orders') && $order->payment_method === 'cod' && $order->payment_status === 'unpaid' && $order->status !== 'cancelled')
                ->requiresConfirmation()
                ->action(function () {
                    $this->guard('manage orders');
                    $p = $this->record->payments()->where('status', 'pending')->first();
                    $this->run(fn () => app(OrderService::class)->markPaid($p, 'COD collected'), 'Marked as paid');
                }),

            Action::make('shipment')->label('Add shipment')->icon('heroicon-o-truck')
                ->visible(fn () => auth()->user()->can('manage shipping') && in_array($order->status, ['processing', 'shipped'], true))
                ->schema([
                    TextInput::make('courier')->required(),
                    TextInput::make('tracking_number')->required(),
                    TextInput::make('tracking_url')->url(),
                ])
                ->action(function (array $data) {
                    $this->guard('manage shipping');
                    $this->run(function () use ($data) {
                        $shipment = Shipment::create($data + ['order_id' => $this->record->id, 'status' => 'in_transit', 'shipped_at' => now()]);
                        foreach ($this->record->items as $item) {
                            $remaining = $item->quantity - $item->shipped_quantity;
                            if ($remaining > 0) {
                                $shipment->items()->create(['order_item_id' => $item->id, 'quantity' => $remaining]);
                                $item->update(['shipped_quantity' => $item->quantity]);
                            }
                        }
                        $this->record->update(['fulfillment_status' => 'fulfilled']);
                        if ($this->record->status === 'processing') {
                            app(OrderService::class)->transition($this->record, 'shipped', "Shipped via {$data['courier']} {$data['tracking_number']}", auth()->user());
                        }
                    }, 'Shipment added');
                }),

            Action::make('refund')->label('Refund')->icon('heroicon-o-receipt-refund')->color('danger')
                ->visible(fn () => auth()->user()->can('process refunds') && in_array($order->payment_status, ['paid', 'partially_refunded', 'refund_due'], true))
                ->schema([
                    TextInput::make('amount')->numeric()->required()->default(fn () => bcsub((string) $this->record->grand_total, (string) $this->record->refunded_total, 2)),
                    Textarea::make('reason')->required(),
                ])
                ->requiresConfirmation()
                ->action(function (array $data) {
                    $this->guard('process refunds');
                    $this->run(function () use ($data) {
                        $refund = app(OrderService::class)->refund($this->record, bcadd((string) $data['amount'], '0', 2), $data['reason'], auth()->user());
                        AuditLogger::log('Refund.issued', $refund, ['amount' => $data['amount'], 'order' => $this->record->reference]);
                    }, 'Refund recorded (issue the money through your bank/gateway)');
                }),
        ];
    }
}
