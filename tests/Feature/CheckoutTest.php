<?php

use App\Models\Coupon;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Orders\OrderService;

beforeEach(function () {
    seedShipping();
    $this->variant = makeVariant(['wholesale_enabled' => false], ['price' => 1000, 'stock_quantity' => 10]);
});

function addToCart($test, $variant, int $qty = 1)
{
    return $test->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => $qty]);
}

it('adds to the cart and shows server-computed totals', function () {
    addToCart($this, $this->variant, 2)->assertRedirect('/cart');
    $this->get('/cart')->assertOk()->assertSee('Rs 2,000');
});

it('refuses to add more than the available stock', function () {
    addToCart($this, $this->variant, 11)->assertSessionHasErrors('cart');
    expect(\App\Models\CartItem::count())->toBe(0);
});

it('ignores any client-submitted price', function () {
    $this->post('/cart/add', ['variant_id' => $this->variant->id, 'quantity' => 1, 'price' => 1]);
    $this->post('/checkout', checkoutPayload(['unit_price' => 1, 'grand_total' => 1]));
    expect(Order::first()->grand_total)->toBe('1250.00'); // 1000 + 250 shipping
});

it('places a COD order: snapshots items, deducts stock, writes movement and history', function () {
    addToCart($this, $this->variant, 3);
    $this->post('/checkout', checkoutPayload())->assertRedirect();

    $order = Order::with('items', 'payments', 'histories')->first();
    expect($order->status)->toBe('pending')
        ->and($order->payment_method)->toBe('cod')
        ->and($order->payment_status)->toBe('unpaid')
        ->and($order->subtotal)->toBe('3000.00')->and($order->shipping_total)->toBe('250.00')->and($order->grand_total)->toBe('3250.00')
        ->and($order->items)->toHaveCount(1)
        ->and($order->items[0]->sku)->toBe($this->variant->sku)->and($order->items[0]->unit_price)->toBe('1000.00')
        ->and($order->payments)->toHaveCount(1)->and($order->histories)->toHaveCount(1)
        ->and($this->variant->fresh()->stock_quantity)->toBe(7)
        ->and(StockMovement::where('reference_id', $order->id)->sum('quantity_change'))->toEqual(-3);
    expect(\App\Models\CartItem::count())->toBe(0); // cart emptied
});

it('keeps historical order prices when the product price changes later', function () {
    addToCart($this, $this->variant, 1);
    $this->post('/checkout', checkoutPayload());
    $this->variant->update(['price' => 5000]);
    expect(Order::first()->items->first()->unit_price)->toBe('1000.00');
});

it('applies free shipping over the threshold', function () {
    addToCart($this, $this->variant, 6); // 6000 >= 5000
    $this->post('/checkout', checkoutPayload());
    expect(Order::first()->shipping_total)->toBe('0.00');
});

it('rejects checkout for a city outside every zone / an invalid shipping method', function () {
    addToCart($this, $this->variant, 1);
    $this->post('/checkout', checkoutPayload(['shipping_method_id' => 99999]))->assertSessionHasErrors('checkout');
    expect(Order::count())->toBe(0)->and($this->variant->fresh()->stock_quantity)->toBe(10);
});

it('validates required checkout fields', function () {
    addToCart($this, $this->variant, 1);
    $this->post('/checkout', [])->assertSessionHasErrors(['email', 'phone', 'shipping.city', 'payment_method', 'terms']);
});

it('refuses to oversell the last unit across two buyers', function () {
    $this->variant->update(['stock_quantity' => 1]);

    addToCart($this, $this->variant, 1);
    // A second shopper's cart is built before the first checks out.
    $other = \App\Models\Cart::create(['session_id' => 'other-session']);
    $other->items()->create(['product_variant_id' => $this->variant->id, 'quantity' => 1]);

    $this->post('/checkout', checkoutPayload())->assertRedirect();
    expect($this->variant->fresh()->stock_quantity)->toBe(0);

    $svc = app(\App\Services\Orders\CheckoutService::class);
    $lines = [['variant_id' => $this->variant->id, 'quantity' => 1]];
    $payload = checkoutPayload(['idempotency_key' => 'second-buyer'])['shipping'];
    expect(fn () => $svc->place([
        'email' => 'b@example.com', 'phone' => '1', 'shipping_address' => $payload, 'billing_address' => $payload,
        'shipping_method_id' => \App\Models\ShippingMethod::where('audience', 'retail')->value('id'), 'payment_method' => 'cod', 'idempotency_key' => 'second-buyer',
    ], $lines, null))->toThrow(\App\Services\Orders\CheckoutException::class);

    expect(Order::count())->toBe(1)->and($this->variant->fresh()->stock_quantity)->toBe(0);
});

it('does not create a duplicate order on double submit with the same idempotency key', function () {
    addToCart($this, $this->variant, 1);
    $payload = checkoutPayload();
    $this->post('/checkout', $payload);
    $this->post('/checkout', $payload);

    expect(Order::count())->toBe(1)->and($this->variant->fresh()->stock_quantity)->toBe(9);
});

it('creates a pending bank-transfer payment with the reference and instructions', function () {
    StoreSetting::updateOrCreate(['key' => 'bank_name'], ['value' => 'Demo Bank']);
    addToCart($this, $this->variant, 1);
    $this->post('/checkout', checkoutPayload(['payment_method' => 'bank_transfer']));
    $order = Order::first();

    expect($order->payment_status)->toBe('pending')->and($order->payments->first()->reference)->toBe($order->reference);
    $this->get(route('order.show', $order->reference))->assertOk()->assertSee('Complete your bank transfer')->assertSee('Demo Bank');
});

it('rejects disabled or unknown payment methods', function () {
    StoreSetting::updateOrCreate(['key' => 'payment_cod_enabled'], ['value' => '0']);
    \Illuminate\Support\Facades\Cache::flush();
    addToCart($this, $this->variant, 1);
    $this->post('/checkout', checkoutPayload(['payment_method' => 'cod']))->assertSessionHasErrors('checkout');
    $this->post('/checkout', checkoutPayload(['payment_method' => 'jazzcash']))->assertSessionHasErrors('checkout');
    expect(Order::count())->toBe(0);
});

it('requires login when guest checkout is disabled', function () {
    StoreSetting::updateOrCreate(['key' => 'guest_checkout'], ['value' => '0']);
    \Illuminate\Support\Facades\Cache::flush();
    addToCart($this, $this->variant, 1);
    $this->get('/checkout')->assertRedirect('/login');
});

// ---- Coupons ----
function coupon(array $o = []): Coupon
{
    return Coupon::create(array_merge(['code' => 'SAVE10', 'type' => 'percent', 'value' => 10, 'is_active' => true], $o));
}

it('applies a percentage coupon and records usage', function () {
    coupon();
    addToCart($this, $this->variant, 2);
    $this->post('/cart/coupon', ['coupon' => 'save10'])->assertSessionHasNoErrors();
    $this->post('/checkout', checkoutPayload());

    $o = Order::first();
    expect($o->discount_total)->toBe('200.00')->and($o->grand_total)->toBe('2050.00')->and($o->coupon_code)->toBe('SAVE10')
        ->and(Coupon::first()->used_count)->toBe(1);
});

it('rejects invalid, expired, inactive, under-minimum and exhausted coupons', function (array $attrs) {
    coupon($attrs);
    addToCart($this, $this->variant, 1);
    $this->post('/cart/coupon', ['coupon' => 'SAVE10'])->assertSessionHasErrors('coupon');
})->with([
    'expired' => [['expires_at' => now()->subDay()]],
    'future' => [['starts_at' => now()->addDay()]],
    'inactive' => [['is_active' => false]],
    'min spend' => [['min_subtotal' => 99999]],
    'exhausted' => [['usage_limit' => 1, 'used_count' => 1]],
    'wholesale only' => [['audience' => 'wholesale']],
]);

it('caps fixed discounts at the goods total and honours max discount', function () {
    coupon(['code' => 'BIG', 'type' => 'fixed', 'value' => 999999]);
    addToCart($this, $this->variant, 1);
    $this->post('/cart/coupon', ['coupon' => 'BIG']);
    $this->post('/checkout', checkoutPayload());
    expect(Order::first()->discount_total)->toBe('1000.00');
});

it('gives free shipping with a free-shipping coupon', function () {
    coupon(['code' => 'SHIPFREE', 'type' => 'free_shipping', 'value' => 0]);
    addToCart($this, $this->variant, 1);
    $this->post('/cart/coupon', ['coupon' => 'SHIPFREE']);
    $this->post('/checkout', checkoutPayload());
    expect(Order::first()->shipping_total)->toBe('0.00');
});

// ---- Cart persistence / merge ----
it('merges the guest cart into the account cart on login', function () {
    $user = User::factory()->create(['password' => 'password123']);
    addToCart($this, $this->variant, 2);
    $this->post('/login', ['email' => $user->email, 'password' => 'password123'])->assertRedirect();

    $cart = app(CartService::class)->current($user->fresh());
    expect($cart->items()->sum('quantity'))->toEqual(2);
});

// ---- Order lifecycle ----
function placedOrder($test, $variant, string $method = 'cod', int $qty = 2): Order
{
    addToCart($test, $variant, $qty);
    $test->post('/checkout', checkoutPayload(['payment_method' => $method]));

    return Order::latest('id')->first();
}

it('enforces valid order status transitions', function () {
    $order = placedOrder($this, $this->variant);
    $svc = app(OrderService::class);

    expect(fn () => $svc->transition($order, 'delivered'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $svc->transition($order, 'shipped'))->toThrow(InvalidArgumentException::class);
    $svc->transition($order, 'confirmed');
    $svc->transition($order->fresh(), 'processing');
    $svc->transition($order->fresh(), 'shipped');
    $svc->transition($order->fresh(), 'delivered');
    expect($order->fresh()->status)->toBe('delivered')->and($order->histories()->count())->toBe(5);
    expect(fn () => $svc->transition($order->fresh(), 'cancelled'))->toThrow(InvalidArgumentException::class);
});

it('restores stock and coupon usage when an order is cancelled, exactly once', function () {
    coupon();
    addToCart($this, $this->variant, 2);
    $this->post('/cart/coupon', ['coupon' => 'SAVE10']);
    $this->post('/checkout', checkoutPayload());
    $order = Order::first();
    expect($this->variant->fresh()->stock_quantity)->toBe(8);

    app(OrderService::class)->transition($order, 'cancelled', 'customer request');
    expect($this->variant->fresh()->stock_quantity)->toBe(10)->and(Coupon::first()->used_count)->toBe(0)
        ->and($order->fresh()->payment_status)->toBe('cancelled');
    expect(fn () => app(OrderService::class)->transition($order->fresh(), 'cancelled'))->toThrow(InvalidArgumentException::class);
    expect($this->variant->fresh()->stock_quantity)->toBe(10);
});

it('marks COD payments paid on delivery', function () {
    $order = placedOrder($this, $this->variant);
    $svc = app(OrderService::class);
    foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $s) {
        $svc->transition($order->fresh(), $s);
    }
    expect($order->fresh()->payment_status)->toBe('paid')->and($order->payments()->first()->status)->toBe('successful');
});

it('verifies a bank transfer, confirms the order and then supports partial then full refund', function () {
    $order = placedOrder($this, $this->variant, 'bank_transfer');
    $admin = User::factory()->create();
    $svc = app(OrderService::class);

    $svc->confirmBankTransfer($order, 'TXN123', $admin);
    $order->refresh();
    expect($order->payment_status)->toBe('paid')->and($order->status)->toBe('confirmed');

    $svc->refund($order, '500.00', 'goods damaged', $admin, [$order->items->first()->id => 1]);
    $order->refresh();
    expect($order->payment_status)->toBe('partially_refunded')->and($order->refunded_total)->toBe('500.00')
        ->and($this->variant->fresh()->stock_quantity)->toBe(9);

    expect(fn () => $svc->refund($order, '99999.00', 'too much', $admin))->toThrow(InvalidArgumentException::class);
    $svc->refund($order, bcsub((string) $order->grand_total, '500.00', 2), 'rest', $admin);
    expect($order->fresh()->payment_status)->toBe('refunded');
});

it('refuses to refund unpaid orders', function () {
    $order = placedOrder($this, $this->variant);
    expect(fn () => app(OrderService::class)->refund($order, '10.00', 'x', User::factory()->create()))->toThrow(InvalidArgumentException::class);
});

// ---- Data isolation / guest access ----
it('shows a customer only their own orders', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $this->actingAs($alice);
    addToCart($this, $this->variant, 1);
    $this->post('/checkout', checkoutPayload());
    $ref = Order::first()->reference;
    expect(Order::first()->user_id)->toBe($alice->id);

    $this->actingAs($alice)->get("/account/orders/{$ref}")->assertOk();
    $this->actingAs($bob)->get("/account/orders/{$ref}")->assertNotFound();
    $this->actingAs($bob)->get(route('order.show', $ref))->assertNotFound();
    $this->actingAs($bob)->get(route('account.orders.invoice', $ref))->assertNotFound();
    $this->actingAs($bob)->get('/account/orders')->assertDontSee($ref);
});

it('lets a guest see their order only via the just-placed session, a signed link, or reference+email lookup', function () {
    $order = placedOrder($this, $this->variant);
    $this->get(route('order.show', $order->reference))->assertOk(); // same session that placed it

    auth()->logout();
    $this->flushSession();
    $this->get(route('order.show', $order->reference))->assertNotFound();
    $this->get(\Illuminate\Support\Facades\URL::signedRoute('order.show', $order->reference))->assertOk();
    $this->get(route('order.show', $order->reference).'?signature=forged&expires=9999999999')->assertNotFound();

    $this->post('/track-order', ['reference' => $order->reference, 'email' => 'wrong@example.com'])->assertSessionHasErrors('reference');
    $this->post('/track-order', ['reference' => $order->reference, 'email' => 'buyer@example.com'])->assertRedirect();
});

it('shows the product photo (colour-specific when available) in the cart and checkout', function () {
    $a = $this->variant;
    $b = $a->product->variants()->create(['sku' => 'IMG-B', 'name' => 'Blue', 'color' => 'Blue', 'price' => 1000, 'stock_quantity' => 5]);
    \App\Models\ProductImage::create(['product_id' => $a->product_id, 'path' => 'products/main.webp', 'alt' => 'main', 'sort_order' => 0]);
    \App\Models\ProductImage::create(['product_id' => $a->product_id, 'product_variant_id' => $b->id, 'path' => 'products/blue.webp', 'alt' => 'blue', 'sort_order' => 1]);

    addToCart($this, $b, 1);
    $this->get('/cart')->assertSee('products/blue.webp', false)->assertDontSee('products/main.webp', false);
    $this->get('/checkout')->assertSee('products/blue.webp', false);

    $this->post('/cart/add', ['variant_id' => $a->id, 'quantity' => 1]);
    $this->get('/cart')->assertSee('products/main.webp', false); // no own photo -> product's main photo
});

it('hides bank transfer until a bank account number is configured, and shows the account at checkout once it is', function () {
    StoreSetting::updateOrCreate(['key' => 'bank_account_number'], ['value' => '']);
    \Illuminate\Support\Facades\Cache::flush();
    addToCart($this, $this->variant, 1);
    $this->get('/checkout')->assertDontSee('Bank transfer');
    $this->post('/checkout', checkoutPayload(['payment_method' => 'bank_transfer']))->assertSessionHasErrors('checkout');

    StoreSetting::updateOrCreate(['key' => 'bank_account_number'], ['value' => 'PK36SCBL0000001123456702']);
    StoreSetting::updateOrCreate(['key' => 'bank_name'], ['value' => 'Demo Bank']);
    \Illuminate\Support\Facades\Cache::flush();
    $this->get('/checkout')->assertSee('Bank transfer')->assertSee('PK36SCBL0000001123456702')->assertSee('No card details are needed');
});
