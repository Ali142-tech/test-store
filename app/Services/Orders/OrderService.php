<?php

namespace App\Services\Orders;

use App\Events\OrderStatusChanged;
use App\Models\BundleItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Inventory\StockService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderService
{
    public function __construct(private StockService $stock) {}

    public function transition(Order $order, string $to, ?string $note = null, ?User $by = null): Order
    {
        return DB::transaction(function () use ($order, $to, $note, $by) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            if (! $order->canTransitionTo($to)) {
                throw new InvalidArgumentException("Cannot change order from {$order->status} to {$to}.");
            }
            $from = $order->status;

            if ($to === 'cancelled') {
                $this->restockOrder($order, $by);
                if ($order->coupon_code) {
                    Coupon::where('code', $order->coupon_code)->where('used_count', '>', 0)->decrement('used_count');
                }
                // Paid money must be returned by an explicit refund; flag it so it is not forgotten.
                $order->payment_status = match ($order->payment_status) {
                    'paid' => 'refund_due',
                    'pending', 'unpaid' => 'cancelled',
                    default => $order->payment_status,
                };
                $order->payments()->whereIn('status', ['pending'])->update(['status' => 'cancelled']);
            }

            if ($to === 'delivered' && $order->payment_method === 'cod') {
                foreach ($order->payments()->where('status', 'pending')->get() as $p) {
                    $this->markPaid($p, 'COD collected on delivery');
                }
                $order->refresh();
            }

            $order->status = $to;
            $order->save();
            $order->histories()->create(['from_status' => $from, 'to_status' => $to, 'note' => $note, 'changed_by' => $by?->id]);

            OrderStatusChanged::dispatch($order, $from);

            return $order;
        });
    }

    public function markPaid(Payment $payment, ?string $reference = null): void
    {
        DB::transaction(function () use ($payment, $reference) {
            $payment->update(['status' => 'successful', 'paid_at' => now(), 'reference' => $reference ?? $payment->reference]);
            $payment->order()->update(['payment_status' => 'paid']);
        });
    }

    /** Admin verifies a bank transfer: marks paid and confirms a pending order. */
    public function confirmBankTransfer(Order $order, ?string $reference, User $by): Order
    {
        $payment = $order->payments()->where('method', 'bank_transfer')->where('status', 'pending')->firstOrFail();
        $this->markPaid($payment, $reference);
        $order->refresh();

        return $order->status === 'pending' ? $this->transition($order, 'confirmed', 'Bank transfer verified', $by) : $order;
    }

    /**
     * Refund (full or partial) against a paid order. Optionally restock returned units.
     *
     * @param  array<int,int>  $restock  order_item_id => quantity to put back on the shelf
     */
    public function refund(Order $order, string $amount, string $reason, User $by, array $restock = []): Refund
    {
        return DB::transaction(function () use ($order, $amount, $reason, $by, $restock) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $payment = $order->payments()->where('status', 'successful')->latest('id')->first();

            if (! $payment || ! in_array($order->payment_status, ['paid', 'partially_refunded', 'refund_due'], true)) {
                throw new InvalidArgumentException('Only paid orders can be refunded.');
            }
            $refundable = bcsub((string) $order->grand_total, (string) $order->refunded_total, 2);
            if (bccomp($amount, '0', 2) <= 0 || bccomp($amount, $refundable, 2) > 0) {
                throw new InvalidArgumentException("Refund must be between 0.01 and {$refundable}.");
            }

            $refund = Refund::create(['payment_id' => $payment->id, 'order_id' => $order->id, 'amount' => $amount, 'reason' => $reason, 'processed_by' => $by->id]);

            $order->refunded_total = bcadd((string) $order->refunded_total, $amount, 2);
            $full = bccomp((string) $order->refunded_total, (string) $order->grand_total, 2) >= 0;
            $order->payment_status = $full ? 'refunded' : 'partially_refunded';
            $order->save();
            if ($full) {
                $payment->update(['status' => 'refunded']);
            }

            foreach ($restock as $itemId => $qty) {
                $item = OrderItem::where('order_id', $order->id)->findOrFail($itemId);
                $this->restockItem($item, (int) $qty, $order, $by, 'return');
            }

            if ($full && $order->canTransitionTo('refunded')) {
                $from = $order->status;
                $order->update(['status' => 'refunded']);
                $order->histories()->create(['from_status' => $from, 'to_status' => 'refunded', 'note' => $reason, 'changed_by' => $by->id]);
            }

            return $refund;
        });
    }

    public function restockItem(OrderItem $item, int $qty, Order $order, ?User $by, string $reason): void
    {
        $qty = min($qty, $item->quantity - $item->refunded_quantity);
        if ($qty <= 0 || ! $item->product_variant_id) {
            return;
        }
        $variant = $item->variant()->with('product')->first();
        if ($variant->product->type === 'bundle') {
            foreach (BundleItem::where('bundle_product_id', $variant->product_id)->get() as $c) {
                $this->stock->add($c->product_variant_id, $c->quantity * $qty, $reason, $order, $by?->id);
            }
        } else {
            $this->stock->add($variant->id, $qty, $reason, $order, $by?->id);
        }
        $item->increment('refunded_quantity', $qty);
    }

    private function restockOrder(Order $order, ?User $by): void
    {
        foreach ($order->items as $item) {
            $this->restockItem($item, $item->quantity - $item->refunded_quantity, $order, $by, 'cancellation');
        }
    }
}
