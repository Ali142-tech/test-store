<?php

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentWebhookProcessor;

/** An online-payment order, built directly so the webhook processor can be tested in isolation. */
function onlineOrder(string $method = 'card'): Order
{
    $o = Order::create([
        'reference' => 'ORD-TEST-'.uniqid(), 'email' => 'a@b.c', 'phone' => '1', 'status' => 'pending', 'payment_status' => 'pending', 'payment_method' => $method,
        'subtotal' => 1000, 'grand_total' => 1250, 'shipping_total' => 250, 'shipping_address' => [], 'billing_address' => [], 'placed_at' => now(),
    ]);
    $o->payments()->create(['method' => $method, 'status' => 'pending', 'amount' => 1250, 'currency' => 'PKR']);

    return $o;
}

it('marks the payment and order paid on a valid successful callback', function () {
    $o = onlineOrder();
    $r = app(PaymentWebhookProcessor::class)->process('card', 'evt_1', $o->reference, '1250.00', 'PKR', true);

    expect($r)->toBe('processed')->and($o->fresh()->payment_status)->toBe('paid')->and($o->payments()->first()->status)->toBe('successful');
});

it('is idempotent: a replayed webhook changes nothing', function () {
    $o = onlineOrder();
    $p = app(PaymentWebhookProcessor::class);
    $p->process('card', 'evt_1', $o->reference, '1250.00', 'PKR', true);

    expect($p->process('card', 'evt_1', $o->reference, '1250.00', 'PKR', true))->toBe('duplicate')
        ->and($p->process('card', 'evt_2', $o->reference, '1250.00', 'PKR', true))->toBe('duplicate')
        ->and(PaymentTransaction::where('status', 'successful')->count())->toBe(1);
});

it('rejects wrong amounts, wrong currency, and unknown order references', function () {
    $o = onlineOrder();
    $p = app(PaymentWebhookProcessor::class);

    expect($p->process('card', 'e1', $o->reference, '1.00', 'PKR', true))->toBe('rejected')
        ->and($p->process('card', 'e2', $o->reference, '1250.00', 'USD', true))->toBe('rejected')
        ->and($p->process('card', 'e3', 'NOPE', '1250.00', 'PKR', true))->toBe('rejected')
        ->and($o->fresh()->payment_status)->toBe('pending');
});

it('records failed payments without paying the order', function () {
    $o = onlineOrder();
    app(PaymentWebhookProcessor::class)->process('card', 'e1', $o->reference, '1250.00', 'PKR', false);
    expect($o->fresh()->payment_status)->toBe('failed')->and($o->payments()->first()->status)->toBe('failed');
});

it('does not expose unconfigured online gateways as checkout options', function () {
    seedShipping();
    $methods = app(\App\Services\Payments\PaymentManager::class)->available('retail')->keys()->all();
    expect($methods)->toBe(['cod', 'bank_transfer'])->not->toContain('jazzcash')->not->toContain('easypaisa')->not->toContain('card');
});
