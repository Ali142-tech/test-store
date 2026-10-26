<?php

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Resources\Quotes\Pages\EditQuote;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\WholesaleApplications\Pages\ListWholesaleApplications;
use App\Mail\QuoteMail;
use App\Mail\WholesaleDecisionMail;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Quote;
use App\Models\Review;
use App\Models\StockMovement;
use App\Models\StoreSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    Filament::setCurrentPanel('admin');
    seedShipping();
});

function placeOrder($test, $variant, string $method = 'cod', int $qty = 2): Order
{
    $test->post('/cart/add', ['variant_id' => $variant->id, 'quantity' => $qty]);
    $test->post('/checkout', checkoutPayload(['payment_method' => $method]));
    auth()->logout();

    return Order::latest('id')->first();
}

// ---- Wholesale approval ----
it('approves a wholesale application: grants access, emails the customer, audits it', function () {
    Mail::fake();
    $u = wholesaleUser('pending');
    $u->forceFill(['customer_type' => 'retail'])->save();
    $this->actingAs(staff('Wholesale Manager'));

    Livewire::test(ListWholesaleApplications::class)->callAction(TestAction::make('approve')->table($u->wholesaleApplication));

    expect($u->fresh()->isApprovedWholesale())->toBeTrue()->and($u->fresh()->wholesaleApplication->reviewed_by)->not->toBeNull();
    Mail::assertQueued(WholesaleDecisionMail::class, fn ($m) => $m->hasTo($u->email));
    expect(AuditLog::where('action', 'Wholesale.approved')->exists())->toBeTrue();
});

it('rejecting a previously approved application removes wholesale access', function () {
    Mail::fake();
    $u = wholesaleUser();
    $this->actingAs(staff('Wholesale Manager'));
    Livewire::test(ListWholesaleApplications::class)->callAction(TestAction::make('reject')->table($u->wholesaleApplication), ['notes' => 'Documents unclear']);

    expect($u->fresh()->isApprovedWholesale())->toBeFalse()->and($u->fresh()->customer_type)->toBe('retail')
        ->and($u->fresh()->wholesaleApplication->admin_notes)->toBe('Documents unclear');
});

it('does not let non-wholesale staff approve applications', function () {
    $u = wholesaleUser('pending');
    $this->actingAs(staff('Order Manager'));
    expect(fn () => app(\App\Services\Wholesale\WholesaleApprovalService::class)->approve($u->wholesaleApplication, auth()->user()))->toThrow(InvalidArgumentException::class);
    expect($u->fresh()->wholesaleApplication->status)->toBe('pending');
});

// ---- Orders ----
it('lets an order manager move an order through its lifecycle and verify a bank transfer', function () {
    $v = makeVariant(['wholesale_enabled' => false], ['stock_quantity' => 10]);
    $order = placeOrder($this, $v, 'bank_transfer');
    $this->actingAs(staff('Order Manager'));

    Livewire::test(ViewOrder::class, ['record' => $order->id])->callAction('confirmBank', ['reference' => 'TXN-9'])->assertNotified();
    expect($order->fresh()->payment_status)->toBe('paid')->and($order->fresh()->status)->toBe('confirmed');

    Livewire::test(ViewOrder::class, ['record' => $order->id])->callAction('changeStatus', ['status' => 'processing']);
    Livewire::test(ViewOrder::class, ['record' => $order->id])->callAction('shipment', ['courier' => 'TCS', 'tracking_number' => 'T123', 'tracking_url' => 'https://example.com/t/T123']);

    $order->refresh();
    expect($order->status)->toBe('shipped')->and($order->fulfillment_status)->toBe('fulfilled')->and($order->shipments)->toHaveCount(1)
        ->and($order->items->first()->shipped_quantity)->toBe(2);
});

it('rejects invalid status jumps from the admin UI', function () {
    $v = makeVariant(['wholesale_enabled' => false]);
    $order = placeOrder($this, $v);
    $this->actingAs(staff('Order Manager'));

    Livewire::test(ViewOrder::class, ['record' => $order->id])->callAction('changeStatus', ['status' => 'delivered'])->assertHasActionErrors(['status']); // not an allowed option
    expect($order->fresh()->status)->toBe('pending');
});

it('restricts refunds to staff with the refund permission', function () {
    $v = makeVariant(['wholesale_enabled' => false]);
    $order = placeOrder($this, $v, 'bank_transfer');
    app(\App\Services\Orders\OrderService::class)->markPaid($order->payments()->first());

    $this->actingAs(staff('Order Manager'));
    Livewire::test(ViewOrder::class, ['record' => $order->id])->assertActionHidden('refund');

    $this->actingAs(staff('Store Manager'));
    Livewire::test(ViewOrder::class, ['record' => $order->id])->assertActionVisible('refund')
        ->callAction('refund', ['amount' => '100.00', 'reason' => 'Damaged']);
    expect($order->fresh()->refunded_total)->toBe('100.00')->and(AuditLog::where('action', 'Refund.issued')->exists())->toBeTrue();
});

// ---- Quotes ----
it('sends a quote with negotiated prices and shipping, and conversion honours both', function () {
    Mail::fake();
    $v = makeVariant(['wholesale_enabled' => true], ['price' => 1000, 'stock_quantity' => 1000]);
    $u = wholesaleUser();
    $this->actingAs($u)->post('/wholesale/quotes', ['items' => [['sku' => $v->sku, 'quantity' => 100]]]);
    $quote = Quote::first();

    $this->actingAs(staff('Wholesale Manager'));
    Livewire::test(EditQuote::class, ['record' => $quote->reference])
        ->fillForm(['items' => ['record-'.$quote->items->first()->id => ['quoted_price' => 640]], 'shipping_amount' => 1500, 'expires_at' => now()->addWeek()->toDateTimeString(), 'admin_notes' => 'Best price'])
        ->callAction('send');

    $quote->refresh();
    expect($quote->status)->toBe('quoted')->and($quote->quoted_total)->toBe('64000.00')->and($quote->events()->where('event', 'quoted')->count())->toBe(1);
    Mail::assertQueued(QuoteMail::class);

    $this->actingAs($u)->post("/wholesale/quotes/{$quote->reference}/accept");
    $this->actingAs($u)->post("/wholesale/quotes/{$quote->reference}/checkout", checkoutPayload([
        'shipping_method_id' => \App\Models\ShippingMethod::where('audience', 'wholesale')->value('id'), 'payment_method' => 'bank_transfer',
    ]));
    $order = Order::first();
    expect($order->subtotal)->toBe('64000.00')->and($order->shipping_total)->toBe('1500.00')->and($order->grand_total)->toBe('65500.00');
});

// ---- Inventory ----
it('adjusts stock only through audited movements and never below zero', function () {
    $v = makeVariant([], ['stock_quantity' => 5]);
    $this->actingAs(staff('Inventory Manager'));
    $component = Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $v->product, 'pageClass' => EditProduct::class]);

    $component->callAction(TestAction::make('adjustStock')->table($v), ['delta' => 7, 'reason' => 'adjustment', 'note' => 'recount']);
    expect($v->fresh()->stock_quantity)->toBe(12)->and(StockMovement::where('product_variant_id', $v->id)->count())->toBe(1);

    $component->callAction(TestAction::make('adjustStock')->table($v), ['delta' => -50, 'reason' => 'damage']);
    expect($v->fresh()->stock_quantity)->toBe(12)->and(StockMovement::count())->toBe(1);
});

it('lets only authorised staff change prices and see cost prices', function () {
    $v = makeVariant([], ['price' => 1000, 'cost_price' => 400]);
    $this->actingAs(staff('Inventory Manager')); // may see cost, may NOT change prices
    Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $v->product, 'pageClass' => EditProduct::class])
        ->callAction(TestAction::make('edit')->table($v), ['name' => 'Renamed', 'sku' => $v->sku, 'price' => 1, 'cost_price' => 1]);
    $v->refresh();
    expect($v->name)->toBe('Renamed')->and($v->price)->toBe('1000.00')->and($v->cost_price)->toBe('400.00');

    $this->actingAs(staff('Store Manager'));
    Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $v->product, 'pageClass' => EditProduct::class])
        ->callAction(TestAction::make('edit')->table($v), ['name' => 'Renamed', 'sku' => $v->sku, 'price' => 1200]);
    expect($v->fresh()->price)->toBe('1200.00');
    expect(AuditLog::where('action', 'ProductVariant.updated')->latest('id')->first()->changes)->toHaveKey('price');
});

it('hides cost price from staff without the permission', function () {
    $v = makeVariant([], ['cost_price' => 400]);
    $this->actingAs(staff('Wholesale Manager')); // not allowed into the product catalogue at all
    $this->get(\App\Filament\Resources\Products\ProductResource::getUrl('edit', ['record' => $v->product]))->assertForbidden();
});

it('records a slug redirect when a product slug changes, and returns 410 for archived products', function () {
    $v = makeVariant();
    $old = $v->product->slug;
    $v->product->update(['slug' => 'brand-new-slug']);

    $this->get("/product/{$old}")->assertRedirect('/product/brand-new-slug')->assertStatus(301);
    $this->get('/product/brand-new-slug')->assertOk();

    $v->product->update(['status' => 'archived']);
    $this->get('/product/brand-new-slug')->assertStatus(410);
});

// ---- Reviews / settings / users ----
it('moderates reviews', function () {
    $v = makeVariant();
    $r = Review::create(['product_id' => $v->product_id, 'user_id' => User::factory()->create()->id, 'rating' => 4, 'body' => 'nice']);
    $this->actingAs(staff('Customer Support'));
    Livewire::test(ListReviews::class)->callAction(TestAction::make('approve')->table($r));
    expect($r->fresh()->status)->toBe('approved');
});

it('saves store settings and audits the change', function () {
    $this->actingAs(staff('Super Admin'));
    Livewire::test(\App\Filament\Pages\StoreSettings::class)
        ->fillForm(['store_name' => 'Acme Mobile', 'currency' => 'PKR', 'currency_symbol' => 'Rs', 'tax_rate' => 17, 'guest_checkout' => true, 'payment_cod_enabled' => true, 'payment_bank_enabled' => false])
        ->call('save')->assertHasNoFormErrors();

    expect(StoreSetting::where('key', 'store_name')->value('value'))->toBe('Acme Mobile')
        ->and(StoreSetting::where('key', 'payment_bank_enabled')->value('value'))->toBe('0');
    expect(AuditLog::where('action', 'StoreSetting.updated')->exists())->toBeTrue();
});

it('stops customer support from editing staff accounts or deactivating admins', function () {
    $support = staff('Customer Support');
    $admin = staff('Super Admin');
    $customer = User::factory()->create();
    $this->actingAs($support);

    expect(\App\Filament\Resources\Users\UserResource::canEdit($admin))->toBeFalse()
        ->and(\App\Filament\Resources\Users\UserResource::canEdit($customer))->toBeTrue();
    $this->actingAs($support)->get(\App\Filament\Resources\Users\UserResource::getUrl('edit', ['record' => $admin]))->assertForbidden();
});

it('computes dashboard revenue from received payments only', function () {
    $v = makeVariant(['wholesale_enabled' => false], ['stock_quantity' => 50]);
    $paid = placeOrder($this, $v, 'bank_transfer', 2);      // 2000 + 250 = 2250
    $unpaid = placeOrder($this, $v, 'cod', 1);              // 1000 + 250 = 1250 (COD, not delivered)
    $cancelled = placeOrder($this, $v, 'bank_transfer', 1);
    $svc = app(\App\Services\Orders\OrderService::class);
    $svc->markPaid($paid->payments()->first());
    $svc->transition($cancelled, 'cancelled');
    $svc->refund($paid->fresh(), '250.00', 'shipping goodwill', staff('Super Admin'));

    $m = app(\App\Services\Reports\OrderMetrics::class);
    $s = $m->sales();
    expect($s['gross'])->toBe('2000.00')->and($s['refunds'])->toBe('250.00')->and($s['net'])->toBe('2000.00')->and($s['orders'])->toBe(1)
        ->and($m->outstanding())->toBe('1250.00');
});
