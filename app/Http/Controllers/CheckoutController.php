<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\StoreSetting;
use App\Services\Cart\CartService;
use App\Services\Cart\OrderCalculator;
use App\Services\Orders\CheckoutException;
use App\Services\Orders\CheckoutService;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Handles both cart checkout and accepted-quote checkout (quote lines carry the agreed prices). */
class CheckoutController extends Controller
{
    public function __construct(
        private CartService $carts,
        private OrderCalculator $calculator,
        private CheckoutService $checkout,
        private PaymentManager $payments,
    ) {}

    public function show(Request $request, ?Quote $quote = null)
    {
        $user = $request->user();
        if (! $user && ! StoreSetting::get('guest_checkout', '1')) {
            return redirect()->guest(route('login'));
        }
        [$lines, $coupon] = $this->source($request, $quote);
        if ($lines === []) {
            return redirect()->route('cart.show');
        }

        $default = $user?->addresses()->where('is_default_shipping', true)->first() ?? $user?->addresses()->first();
        $city = old('shipping.city', $default?->city);
        $calc = $this->calculator->calculate($lines, $user, $coupon, $city, null, trusted: $quote !== null, shippingOverride: $quote?->shipping_amount !== null ? (string) $quote->shipping_amount : null);

        return view('checkout.show', [
            'calc' => $calc, 'quote' => $quote, 'default' => $default,
            'methods' => $this->payments->available($calc['audience']),
            'idempotencyKey' => (string) Str::uuid(),
            'action' => $quote ? route('quotes.place', $quote) : route('checkout.place'),
            'summaryUrl' => $quote ? route('quotes.summary', $quote) : route('checkout.summary'),
        ]);
    }

    /** JSON totals for the chosen city / shipping method. Purely informational: placing re-computes everything. */
    public function summary(Request $request, ?Quote $quote = null)
    {
        $data = $request->validate(['city' => 'nullable|string|max:100', 'shipping_method_id' => 'nullable|integer']);
        [$lines, $coupon] = $this->source($request, $quote);
        $calc = $this->calculator->calculate($lines, $request->user(), $coupon, $data['city'] ?? null, $data['shipping_method_id'] ?? null, trusted: $quote !== null, shippingOverride: $quote?->shipping_amount !== null ? (string) $quote->shipping_amount : null);

        return response()->json([
            'subtotal' => $calc['subtotal'], 'discount' => $calc['discount'], 'shipping' => $calc['shipping'], 'tax' => $calc['tax'], 'total' => $calc['total'],
            'errors' => $calc['errors'],
            'shipping_options' => collect($calc['shipping_options'])->map(fn ($o) => ['id' => $o['method']->id, 'name' => $o['method']->name, 'cost' => $o['cost'], 'eta' => $o['eta']])->values(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function place(Request $request, ?Quote $quote = null)
    {
        $user = $request->user();
        if (! $user && ! StoreSetting::get('guest_checkout', '1')) {
            return redirect()->guest(route('login'));
        }

        $addr = fn (string $p) => [
            "$p.full_name" => 'required|string|max:120', "$p.phone" => 'required|string|max:30', "$p.line1" => 'required|string|max:190',
            "$p.line2" => 'nullable|string|max:190', "$p.city" => 'required|string|max:100', "$p.state" => 'nullable|string|max:100',
            "$p.postal_code" => 'nullable|string|max:20', "$p.country" => 'nullable|string|size:2',
        ];
        $same = $request->boolean('billing_same');
        $data = $request->validate([
            'email' => 'required|email:rfc|max:190', 'phone' => 'required|string|max:30',
            'shipping_method_id' => 'required|integer', 'payment_method' => 'required|string|max:30',
            'note' => 'nullable|string|max:1000', 'idempotency_key' => 'required|string|max:64',
            'terms' => 'accepted',
            ...$addr('shipping'), ...($same ? [] : $addr('billing')),
        ]);

        $shipping = $data['shipping'] + ['country' => 'PK'];
        $billing = $same ? $shipping : $data['billing'] + ['country' => 'PK'];
        [$lines, $coupon] = $this->source($request, $quote);

        try {
            $order = $this->checkout->place([
                'email' => $data['email'], 'phone' => $data['phone'], 'shipping_address' => $shipping, 'billing_address' => $billing,
                'shipping_method_id' => (int) $data['shipping_method_id'], 'payment_method' => $data['payment_method'],
                'note' => $data['note'] ?? null, 'coupon' => $coupon, 'idempotency_key' => $data['idempotency_key'],
            ], $lines, $user, $quote);
        } catch (CheckoutException $e) {
            return back()->withInput()->withErrors(['checkout' => $e->errors]);
        }

        if (! $quote) {
            $this->carts->current($user, false)?->items()->delete();
            $this->carts->current($user, false)?->update(['coupon_code' => null]);
        }
        $request->session()->put('order_access.'.$order->reference, true);

        return redirect()->route('order.show', $order->reference);
    }

    /** @return array{0: array<int, array>, 1: ?string} */
    private function source(Request $request, ?Quote $quote): array
    {
        if ($quote) {
            abort_unless($quote->user_id === $request->user()?->id && $quote->status === 'accepted' && (! $quote->expires_at || $quote->expires_at->isFuture()), 403);

            return [$quote->items->map(fn ($i) => ['variant_id' => $i->product_variant_id, 'quantity' => $i->quantity, 'price' => (string) $i->quoted_price])->all(), null];
        }

        $cart = $this->carts->current($request->user());

        return [
            $cart->items()->get()->map(fn ($i) => ['variant_id' => $i->product_variant_id, 'quantity' => $i->quantity])->all(),
            $cart->coupon_code,
        ];
    }
}
