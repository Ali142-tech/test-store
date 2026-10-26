<?php

namespace App\Services\Orders;

use App\Events\OrderPlaced;
use App\Models\BundleItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Quote;
use App\Models\User;
use App\Services\Cart\OrderCalculator;
use App\Services\Inventory\InsufficientStockException;
use App\Services\Inventory\StockService;
use App\Services\Payments\PaymentManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    public function __construct(
        private OrderCalculator $calculator,
        private StockService $stock,
        private PaymentManager $payments,
    ) {}

    /**
     * Places an order. Everything is recomputed inside the transaction after locking the stock rows.
     *
     * @param  array{email:string, phone:string, shipping_address:array, billing_address:array, shipping_method_id:int, payment_method:string, note?:?string, coupon?:?string, idempotency_key:string}  $data
     * @param  array<int, array{variant_id:int, quantity:int, price?:string}>  $lines
     *
     * @throws CheckoutException
     */
    public function place(array $data, array $lines, ?User $user, ?Quote $quote = null): Order
    {
        if ($existing = Order::where('idempotency_key', $data['idempotency_key'])->first()) {
            return $existing; // duplicate submit / double click
        }
        if (! $this->payments->isEnabled($data['payment_method'], $user?->isApprovedWholesale() ? 'wholesale' : 'retail')) {
            throw new CheckoutException(['The selected payment method is not available.']);
        }

        try {
            $order = DB::transaction(function () use ($data, $lines, $user, $quote) {
                // Lock all variants (and bundle components) in a stable order to avoid deadlocks.
                $variantIds = collect($lines)->pluck('variant_id');
                $componentIds = BundleItem::whereIn('bundle_product_id', ProductVariant::whereIn('id', $variantIds)->pluck('product_id'))->pluck('product_variant_id');
                ProductVariant::whereIn('id', $variantIds->merge($componentIds)->unique()->sort()->values())->orderBy('id')->lockForUpdate()->get();

                $calc = $this->calculator->calculate(
                    $lines, $user, $data['coupon'] ?? null, $data['shipping_address']['city'] ?? null,
                    (int) $data['shipping_method_id'], trusted: $quote !== null,
                    shippingOverride: $quote?->shipping_amount !== null ? (string) $quote->shipping_amount : null,
                );
                if ($calc['errors'] || $calc['coupon_error']) {
                    throw new CheckoutException(array_filter([...$calc['errors'], $calc['coupon_error']]));
                }

                $shippingMethod = collect($calc['shipping_options'])->first(fn ($o) => $o['method']->id === (int) $data['shipping_method_id']);

                if ($calc['coupon']) {
                    $coupon = Coupon::where('code', $calc['coupon'])->lockForUpdate()->first();
                    if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
                        throw new CheckoutException(['This coupon has reached its usage limit.']);
                    }
                    $coupon->increment('used_count');
                }

                $order = Order::create([
                    'reference' => $this->newReference(),
                    'idempotency_key' => $data['idempotency_key'],
                    'user_id' => $user?->id,
                    'guest_token' => $user ? null : Str::random(48),
                    'customer_type' => $calc['audience'],
                    'email' => $data['email'], 'phone' => $data['phone'],
                    'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => $data['payment_method'],
                    'currency' => \App\Models\StoreSetting::get('currency', 'PKR'),
                    'subtotal' => $calc['subtotal'], 'discount_total' => $calc['discount'], 'shipping_total' => $calc['shipping'],
                    'tax_total' => $calc['tax'], 'grand_total' => $calc['total'],
                    'coupon_code' => $calc['coupon'],
                    'shipping_method_name' => $shippingMethod['method']->name,
                    'shipping_address' => $data['shipping_address'], 'billing_address' => $data['billing_address'],
                    'customer_note' => $data['note'] ?? null,
                    'quote_id' => $quote?->id, 'placed_at' => now(),
                ]);

                // Spread the order-level discount across lines proportionally (last line takes the remainder).
                $remaining = $calc['discount'];
                foreach ($calc['lines'] as $i => $l) {
                    $share = $i === array_key_last($calc['lines'])
                        ? $remaining
                        : (bccomp($calc['subtotal'], '0', 2) > 0 ? bcdiv(bcmul($calc['discount'], $l['line_total'], 4), $calc['subtotal'], 2) : '0.00');
                    $remaining = bcsub($remaining, $share, 2);

                    /** @var ProductVariant $v */
                    $v = $l['variant'];
                    OrderItem::create([
                        'order_id' => $order->id, 'product_id' => $v->product_id, 'product_variant_id' => $v->id,
                        'product_name' => $v->product->name, 'variant_name' => $v->name, 'sku' => $v->sku,
                        'unit_price' => $l['unit_price'], 'unit_cost' => $v->cost_price, 'price_source' => $l['price_source'],
                        'quantity' => $l['quantity'], 'discount_amount' => $share,
                        'line_total' => bcsub($l['line_total'], $share, 2),
                    ]);

                    $this->deductStock($v, $l['quantity'], $order);
                    $v->product->increment('sales_count', $l['quantity']);
                }

                $order->histories()->create(['from_status' => null, 'to_status' => 'pending', 'note' => 'Order placed', 'changed_by' => $user?->id]);
                $this->payments->initiate($order, $data['payment_method']);

                $quote?->update(['status' => 'converted']);
                $quote?->events()->create(['user_id' => $user?->id, 'event' => 'converted', 'note' => "Converted to order {$order->reference}"]);

                return $order;
            });
        } catch (InsufficientStockException $e) {
            throw new CheckoutException([$e->getMessage()]);
        } catch (UniqueConstraintViolationException) {
            // Concurrent double-submit raced past the idempotency pre-check.
            return Order::where('idempotency_key', $data['idempotency_key'])->firstOrFail();
        }

        OrderPlaced::dispatch($order);

        return $order;
    }

    private function deductStock(ProductVariant $variant, int $qty, Order $order): void
    {
        if ($variant->product->type === 'bundle') {
            foreach (BundleItem::where('bundle_product_id', $variant->product_id)->get() as $component) {
                $this->stock->remove($component->product_variant_id, $component->quantity * $qty, 'sale', $order);
            }

            return;
        }
        $this->stock->remove($variant->id, $qty, 'sale', $order);
    }

    private function newReference(): string
    {
        do {
            $ref = 'ORD-'.now()->format('ymd').'-'.strtoupper(Str::random(5));
        } while (Order::where('reference', $ref)->exists());

        return $ref;
    }
}
