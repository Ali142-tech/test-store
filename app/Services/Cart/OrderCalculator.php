<?php

namespace App\Services\Cart;

use App\Models\ProductVariant;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\Pricing\PriceResolver;
use App\Services\Shipping\ShippingService;

/**
 * Computes every monetary value for a cart / bulk order / converted quote.
 * The browser contributes only variant ids, quantities, a coupon code, a city and a shipping-method id.
 */
class OrderCalculator
{
    public function __construct(
        private PriceResolver $prices,
        private CouponService $coupons,
        private ShippingService $shipping,
    ) {}

    /**
     * @param  array<int, array{variant_id:int, quantity:int, price?:string}>  $input  `price` is honoured only when $trusted (accepted quotes).
     * @return array{lines: array, subtotal: string, discount: string, shipping: string, tax: string, total: string, coupon: ?string, shipping_options: array, errors: array<int,string>, coupon_error: ?string, audience: string}
     */
    public function calculate(array $input, ?User $user, ?string $couponCode = null, ?string $city = null, ?int $shippingMethodId = null, bool $trusted = false): array
    {
        $audience = $user?->isApprovedWholesale() ? 'wholesale' : 'retail';
        $errors = [];
        $lines = [];
        $subtotal = '0.00';
        $weight = 0.0;

        $variants = ProductVariant::with('product', 'tiers')->whereIn('id', collect($input)->pluck('variant_id'))->get()->keyBy('id');

        foreach ($input as $row) {
            $variant = $variants->get($row['variant_id']);
            $qty = (int) $row['quantity'];

            if (! $variant || ! $variant->is_active || $variant->product?->status !== 'active') {
                $errors[] = 'An item in your order is no longer available.';

                continue;
            }
            if ($qty < 1) {
                $errors[] = "Invalid quantity for {$variant->displayName()}.";

                continue;
            }
            if ($qty > $variant->available()) {
                $errors[] = $variant->available() === 0
                    ? "{$variant->displayName()} is out of stock."
                    : "Only {$variant->available()} of {$variant->displayName()} left in stock.";
            }

            if ($trusted && isset($row['price'])) {
                $price = ['price' => bcadd((string) $row['price'], '0', 2), 'source' => 'quote'];
            } else {
                $price = $this->prices->resolve($variant, $qty, $user);
                $min = $this->prices->minimumQuantity($variant, $user);
                if ($qty < $min) {
                    $errors[] = "Minimum order quantity for {$variant->displayName()} is {$min}.";
                }
            }

            $lineTotal = $this->prices->lineTotal($price['price'], $qty);
            $subtotal = bcadd($subtotal, $lineTotal, 2);
            $weight += ((float) $variant->product->weight_grams * $qty) / 1000;

            $lines[] = [
                'variant' => $variant, 'variant_id' => $variant->id, 'product_id' => $variant->product_id,
                'quantity' => $qty, 'unit_price' => $price['price'], 'price_source' => $price['source'],
                'regular_price' => $variant->currentRetailPrice(), 'line_total' => $lineTotal,
            ];
        }

        if ($lines === []) {
            $errors[] = 'Your cart is empty.';
        }

        if ($audience === 'wholesale' && ! $trusted && $lines !== []) {
            $min = (string) StoreSetting::get('wholesale_min_order_value', '0');
            if (bccomp($subtotal, $min, 2) < 0) {
                $errors[] = 'The minimum wholesale order value is '.number_format((float) $min).'.';
            }
        }

        // Coupon (quote-based orders carry negotiated prices and cannot stack coupons).
        $discount = '0.00';
        $freeShipping = false;
        $couponApplied = null;
        $couponError = null;
        if ($couponCode && ! $trusted && $lines !== []) {
            try {
                $r = $this->coupons->evaluate($couponCode, $lines, $subtotal, $user, $audience);
                $discount = $r['discount'];
                $freeShipping = $r['free_shipping'];
                $couponApplied = $r['coupon']->code;
            } catch (CouponException $e) {
                $couponError = $e->getMessage();
            }
        }

        $payable = bcsub($subtotal, $discount, 2);
        $options = $city !== null && $lines !== [] ? $this->shipping->options($city, $payable, $weight, $audience, $freeShipping) : collect();
        $shippingCost = '0.00';
        if ($shippingMethodId) {
            $chosen = $options->first(fn ($o) => $o['method']->id === $shippingMethodId);
            if (! $chosen) {
                $errors[] = 'The selected shipping method is not available for your address.';
            } else {
                $shippingCost = $chosen['cost'];
            }
        }

        $rate = (string) StoreSetting::get('tax_rate', '0');
        $tax = bcadd(bcdiv(bcmul($payable, $rate, 4), '100', 4), '0.005', 2); // half-up
        if (bccomp($rate, '0', 2) === 0) {
            $tax = '0.00';
        }

        return [
            'lines' => $lines, 'subtotal' => $subtotal, 'discount' => $discount, 'shipping' => $shippingCost, 'tax' => $tax,
            'total' => bcadd(bcadd($payable, $shippingCost, 2), $tax, 2),
            'coupon' => $couponApplied, 'coupon_error' => $couponError, 'shipping_options' => $options->all(),
            'errors' => array_values(array_unique($errors)), 'audience' => $audience,
        ];
    }
}
