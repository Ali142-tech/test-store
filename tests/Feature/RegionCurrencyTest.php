<?php

use App\Models\Order;
use App\Models\StoreSetting;
use App\Support\Money;
use App\Support\Region;

beforeEach(function () {
    seedShipping();
    foreach (['rate_usd' => '280', 'rate_gbp' => '370', 'rate_eur' => '325', 'extra_usd' => '30'] as $k => $v) {
        StoreSetting::updateOrCreate(['key' => $k], ['value' => $v]);
    }
    $this->variant = makeVariant(['wholesale_enabled' => false], ['price' => 32000, 'stock_quantity' => 10]);
});

function from(string $country): array
{
    return ['CF-IPCountry' => $country];
}

it('keeps Pakistan and unknown regions in PKR with no fee', function () {
    $this->withHeaders(from('PK'))->get('/shop')->assertOk()->assertSee('Rs 32,000');
    $this->withHeaders(from('AE'))->get('/shop')->assertOk()->assertSee('Rs 32,000');
    $this->get('/shop')->assertOk()->assertSee('Rs 32,000');
});

it('shows US and Canada visitors dollars: PKR price / rate + the $30 fee', function () {
    // 32000 / 280 = 114.29 ; + 30 = 144.29
    $this->withHeaders(from('US'))->get('/shop')->assertOk()->assertSee('$144.29')->assertDontSee('Rs 32,000');
    $this->withHeaders(from('CA'))->get('/shop')->assertSee('$144.29');
});

it('shows the UK pounds and eurozone countries euros, with the same $30 fee converted', function () {
    // (32000 + 30 * 280) / 370 = 109.19 ; / 325 = 124.31
    $this->withHeaders(from('GB'))->get('/shop')->assertSee('£109.19');
    foreach (['DE', 'FR', 'IT', 'ES'] as $c) {
        $this->withHeaders(from($c))->get('/shop')->assertSee('€124.31');
    }
});

it('does not let a visitor pick their own currency in production', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->get('/shop?currency=USD')->assertSee('Rs 32,000');
});

it('uses the admin-set rate and fee', function () {
    StoreSetting::updateOrCreate(['key' => 'rate_usd'], ['value' => '300']);
    StoreSetting::updateOrCreate(['key' => 'extra_usd'], ['value' => '50']);
    // 32000 / 300 = 106.67 ; + 50 = 156.67
    $this->withHeaders(from('US'))->get('/shop')->assertSee('$156.67');
});

it('charges the fee server-side: cart, order and payment stay in PKR and include it', function () {
    $us = from('US');
    $this->withHeaders($us)->post('/cart/add', ['variant_id' => $this->variant->id, 'quantity' => 2])->assertRedirect('/cart');

    // 2 x (32000 + 30*280 = 40400) = 80800 PKR, shown as 2 x $144.29 = $288.57 (80800/280)
    $this->withHeaders($us)->get('/cart')->assertOk()->assertSee('$144.29')->assertSee('$288.57');

    $this->withHeaders($us)->post('/checkout', checkoutPayload(['payment_method' => 'bank_transfer']))->assertRedirect();

    $order = Order::with('items')->first();
    expect($order->currency)->toBe('PKR')
        ->and($order->items[0]->unit_price)->toBe('40400.00')
        ->and($order->subtotal)->toBe('80800.00')
        ->and($order->display_currency)->toBe('USD')
        ->and((float) $order->display_rate)->toBe(280.0)
        ->and($order->payments->first()->amount)->toBe($order->grand_total);

    // The order page, opened from anywhere, keeps showing the currency it was placed in.
    $this->get(route('order.show', $order->reference).'?token='.$order->guest_token)->assertOk();
    expect(Money::order($order->grand_total, $order))->toStartWith('$');
});

it('does not touch prices for Pakistani orders', function () {
    $this->withHeaders(from('PK'))->post('/cart/add', ['variant_id' => $this->variant->id, 'quantity' => 1]);
    $this->withHeaders(from('PK'))->post('/checkout', checkoutPayload());
    $order = Order::first();
    expect($order->items()->first()->unit_price)->toBe('32000.00')->and($order->display_currency)->toBe('PKR');
});

it('exposes the fee only through Region for international visitors', function () {
    expect(Region::extraPkr())->toBe(0.0);
});
