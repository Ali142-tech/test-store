<?php

namespace App\Http\Controllers;

use App\Services\Cart\CartService;
use App\Services\Cart\OrderCalculator;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CartController extends Controller
{
    public function __construct(private CartService $carts, private OrderCalculator $calculator) {}

    public function show(Request $request)
    {
        $cart = $this->carts->current($request->user());
        $items = $cart->items()->get()->map(fn ($i) => ['variant_id' => $i->product_variant_id, 'quantity' => $i->quantity])->all();
        $calc = $this->calculator->calculate($items, $request->user(), $cart->coupon_code);

        // A coupon that stopped being valid is dropped so it cannot linger.
        if ($cart->coupon_code && $calc['coupon_error']) {
            $cart->update(['coupon_code' => null]);
        }

        return view('cart.show', ['calc' => $calc, 'couponError' => $calc['coupon_error'], 'cart' => $cart]);
    }

    public function add(Request $request)
    {
        $data = $request->validate(['variant_id' => 'required|integer', 'quantity' => 'required|integer|min:1|max:10000']);

        try {
            $this->carts->add($this->carts->current($request->user()), $data['variant_id'], $data['quantity'], $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        return $request->boolean('buy_now') ? redirect()->route('checkout.show') : redirect()->route('cart.show')->with('status', 'Added to your cart.');
    }

    public function update(Request $request)
    {
        $data = $request->validate(['variant_id' => 'required|integer', 'quantity' => 'required|integer|min:0|max:10000']);

        try {
            $this->carts->update($this->carts->current($request->user()), $data['variant_id'], $data['quantity'], $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        return back();
    }

    public function remove(Request $request, int $variant)
    {
        $this->carts->remove($this->carts->current($request->user()), $variant);

        return back();
    }

    public function applyCoupon(Request $request)
    {
        $data = $request->validate(['coupon' => 'required|string|max:50']);
        $cart = $this->carts->current($request->user());
        $items = $cart->items()->get()->map(fn ($i) => ['variant_id' => $i->product_variant_id, 'quantity' => $i->quantity])->all();
        $calc = $this->calculator->calculate($items, $request->user(), $data['coupon']);

        if ($calc['coupon_error']) {
            return back()->withErrors(['coupon' => $calc['coupon_error']]);
        }
        $cart->update(['coupon_code' => $calc['coupon']]);

        return back()->with('status', 'Coupon applied.');
    }

    public function removeCoupon(Request $request)
    {
        $this->carts->current($request->user())->update(['coupon_code' => null]);

        return back();
    }
}
