<?php

namespace App\Services\Cart;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;

class CouponService
{
    /**
     * @param  array<int, array{product_id:int, line_total:string}>  $lines
     * @return array{coupon: Coupon, discount: string, free_shipping: bool}
     *
     * @throws CouponException
     */
    public function evaluate(string $code, array $lines, string $subtotal, ?User $user, string $audience): array
    {
        $coupon = Coupon::whereRaw('LOWER(code) = ?', [mb_strtolower(trim($code))])->first();

        if (! $coupon || ! $coupon->is_active) {
            throw new CouponException('This coupon code is not valid.');
        }
        if (($coupon->starts_at && $coupon->starts_at->isFuture()) || ($coupon->expires_at && $coupon->expires_at->isPast())) {
            throw new CouponException('This coupon is not currently active.');
        }
        if ($coupon->audience !== 'all' && $coupon->audience !== $audience) {
            throw new CouponException('This coupon is not available for your account type.');
        }
        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw new CouponException('This coupon has reached its usage limit.');
        }
        if ($coupon->usage_limit_per_user !== null) {
            if (! $user) {
                throw new CouponException('Please sign in to use this coupon.');
            }
            $used = Order::where('user_id', $user->id)->where('coupon_code', $coupon->code)->where('status', '!=', 'cancelled')->count();
            if ($used >= $coupon->usage_limit_per_user) {
                throw new CouponException('You have already used this coupon the maximum number of times.');
            }
        }
        if ($coupon->min_subtotal !== null && bccomp($subtotal, (string) $coupon->min_subtotal, 2) < 0) {
            throw new CouponException('Spend at least '.number_format((float) $coupon->min_subtotal).' to use this coupon.');
        }

        $eligible = collect($lines)->when($coupon->product_ids, fn ($c) => $c->whereIn('product_id', $coupon->product_ids));
        $eligibleTotal = $eligible->reduce(fn ($carry, $l) => bcadd($carry, $l['line_total'], 2), '0.00');

        if ($coupon->type !== 'free_shipping' && bccomp($eligibleTotal, '0', 2) <= 0) {
            throw new CouponException('This coupon does not apply to the items in your cart.');
        }

        $discount = match ($coupon->type) {
            'percent' => bcdiv(bcmul($eligibleTotal, (string) $coupon->value, 4), '100', 2),
            'fixed' => (string) $coupon->value,
            default => '0.00',
        };
        if ($coupon->max_discount !== null && bccomp($discount, (string) $coupon->max_discount, 2) > 0) {
            $discount = (string) $coupon->max_discount;
        }
        if (bccomp($discount, $eligibleTotal, 2) > 0) {
            $discount = $eligibleTotal; // never discount more than the eligible goods
        }

        return ['coupon' => $coupon, 'discount' => bcadd($discount, '0', 2), 'free_shipping' => $coupon->type === 'free_shipping'];
    }
}
