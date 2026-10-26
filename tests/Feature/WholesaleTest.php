<?php

use App\Models\Order;
use App\Models\Quote;
use App\Models\StoreSetting;
use App\Models\User;
use App\Models\WholesaleApplication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedShipping();
    $this->variant = makeVariant([], ['price' => 1000, 'stock_quantity' => 1000, 'wholesale_moq' => 10]);
});

function appPayload(array $o = []): array
{
    return array_merge([
        'business_name' => 'Mobile Hub', 'contact_name' => 'Ali', 'contact_phone' => '0300', 'business_address' => 'Shop 4, Hall Rd', 'city' => 'Lahore',
    ], $o);
}

// ---- Registration & approval ----
it('registers a wholesale applicant as a RETAIL user with a pending application', function () {
    Storage::fake('local');
    $this->post('/wholesale/register', appPayload([
        'name' => 'Ali', 'email' => 'ali@shop.test', 'password' => 'password123', 'password_confirmation' => 'password123',
        'document' => UploadedFile::fake()->create('licence.pdf', 200, 'application/pdf'),
    ]))->assertRedirect('/wholesale');

    $u = User::where('email', 'ali@shop.test')->first();
    expect($u->customer_type)->toBe('retail')->and($u->wholesaleApplication->status)->toBe('pending')->and($u->isApprovedWholesale())->toBeFalse();
    Storage::disk('local')->assertExists($u->wholesaleApplication->document_path);
    expect($u->wholesaleApplication->document_path)->toStartWith('wholesale-documents/');
});

it('rejects dangerous or oversized verification documents', function (string $name, int $kb, string $mime) {
    Storage::fake('local');
    $this->actingAs(User::factory()->create())
        ->post('/wholesale/register', appPayload(['document' => UploadedFile::fake()->create($name, $kb, $mime)]))
        ->assertSessionHasErrors('document');
    expect(WholesaleApplication::count())->toBe(0);
})->with([['shell.php', 10, 'application/x-php'], ['malware.exe', 10, 'application/octet-stream'], ['huge.pdf', 9000, 'application/pdf']]);

it('does not let a user apply twice or self-approve via the form', function () {
    $u = User::factory()->create();
    $this->actingAs($u)->post('/wholesale/register', appPayload(['status' => 'approved', 'customer_type' => 'wholesale']))->assertRedirect();
    expect($u->fresh()->customer_type)->toBe('retail')->and($u->fresh()->wholesaleApplication->status)->toBe('pending');
    $this->actingAs($u)->post('/wholesale/register', appPayload())->assertForbidden();
});

// ---- Server-side access control ----
it('blocks everyone but approved wholesale buyers from wholesale endpoints', function (string $method, string $uri) {
    foreach ([null, User::factory()->create(), wholesaleUser('pending'), wholesaleUser('rejected')] as $who) {
        $req = $who ? $this->actingAs($who) : $this;
        $res = $req->{$method}($uri);
        expect($res->status())->toBeIn([302, 403]);
        if ($who) {
            $res->assertForbidden();
        }
    }
    $this->actingAs(wholesaleUser())->{$method}($uri)->assertSuccessful();
})->with([['get', '/wholesale/bulk-order'], ['get', '/wholesale/quotes'], ['get', '/wholesale/quotes/new'], ['get', '/wholesale/bulk-order/template']]);

it('does not leak tier prices through the bulk price API to retail users', function () {
    $this->actingAs(User::factory()->create())->getJson('/wholesale/bulk-order/price?sku='.$this->variant->sku.'&quantity=100')->assertForbidden();
    $this->actingAs(wholesaleUser())->getJson('/wholesale/bulk-order/price?sku='.$this->variant->sku.'&quantity=100')
        ->assertOk()->assertJson(['unit_price' => '700.00', 'source' => 'tier', 'line_total' => '70000.00']);
});

it('keeps a wholesale user as retail priced until approved, then tier priced', function () {
    $pending = wholesaleUser('pending');
    $this->actingAs($pending)->post('/cart/add', ['variant_id' => $this->variant->id, 'quantity' => 100]);
    $this->actingAs($pending)->get('/cart')->assertSee('Rs 100,000');

    $ok = wholesaleUser();
    $this->actingAs($ok)->post('/cart/add', ['variant_id' => $this->variant->id, 'quantity' => 100]);
    $this->actingAs($ok)->get('/cart')->assertSee('Rs 70,000')->assertSee('Wholesale tier');
});

it('enforces minimum order quantity server-side for wholesale buyers only', function () {
    $this->actingAs(wholesaleUser())->post('/cart/add', ['variant_id' => $this->variant->id, 'quantity' => 5])->assertSessionHasErrors('cart');
    $this->actingAs(User::factory()->create())->post('/cart/add', ['variant_id' => $this->variant->id, 'quantity' => 5])->assertSessionHasNoErrors();
});

it('enforces the minimum wholesale order value at checkout', function () {
    StoreSetting::updateOrCreate(['key' => 'wholesale_min_order_value'], ['value' => '25000']);
    $u = wholesaleUser();
    $this->actingAs($u)->post('/cart/add', ['variant_id' => $this->variant->id, 'quantity' => 10]); // 9,000
    $payload = checkoutPayload(['shipping_method_id' => \App\Models\ShippingMethod::where('audience', 'wholesale')->value('id'), 'payment_method' => 'bank_transfer']);
    $this->actingAs($u)->post('/checkout', $payload)->assertSessionHasErrors('checkout');
    expect(Order::count())->toBe(0);

    $this->actingAs($u)->post('/cart/add', ['variant_id' => $this->variant->id, 'quantity' => 40]); // 50 units @ 800 = 40,000
    $this->actingAs($u)->post('/checkout', $payload)->assertRedirect();
    $o = Order::first();
    expect($o->customer_type)->toBe('wholesale')->and($o->subtotal)->toBe('40000.00')->and($o->items->first()->price_source)->toBe('tier');
});

it('hides COD from wholesale buyers unless enabled and blocks retail shipping rates', function () {
    $u = wholesaleUser();
    $this->actingAs($u)->post('/cart/add', ['variant_id' => $this->variant->id, 'quantity' => 10]);
    $this->actingAs($u)->post('/checkout', checkoutPayload(['payment_method' => 'cod', 'shipping_method_id' => \App\Models\ShippingMethod::where('audience', 'wholesale')->value('id')]))->assertSessionHasErrors('checkout');
    $this->actingAs($u)->post('/checkout', checkoutPayload(['payment_method' => 'bank_transfer'])) // retail shipping method id
        ->assertSessionHasErrors('checkout');
    expect(Order::count())->toBe(0);
});

it('applies negotiated prices for the customer only', function () {
    $u = wholesaleUser();
    \App\Models\WholesalePrice::create(['user_id' => $u->id, 'product_variant_id' => $this->variant->id, 'price' => 600]);
    $this->actingAs($u)->post('/cart/add', ['variant_id' => $this->variant->id, 'quantity' => 10]);
    $this->actingAs($u)->get('/cart')->assertSee('Rs 6,000');

    $other = wholesaleUser();
    $this->actingAs($other)->post('/cart/add', ['variant_id' => $this->variant->id, 'quantity' => 10]);
    $this->actingAs($other)->get('/cart')->assertSee('Rs 9,000');
});

// ---- Bulk ordering ----
it('adds valid bulk lines from text and CSV and reports the invalid ones', function () {
    $u = wholesaleUser();
    $b = makeVariant([], ['sku' => 'BULK-B', 'stock_quantity' => 1000, 'wholesale_moq' => 10]);
    $c = makeVariant([], ['stock_quantity' => 1000, 'wholesale_moq' => 10]);
    $csv = UploadedFile::fake()->createWithContent('order.csv', "sku,quantity\nBULK-B,20\nNOPE-1,5\n");

    $res = $this->actingAs($u)->post('/wholesale/bulk-order', [
        'lines' => $this->variant->sku.", 10\n{$this->variant->sku}, abc\n".$c->sku.', 3', 'csv' => $csv,
    ]);
    $res->assertRedirect('/cart');
    $errors = collect(session('errors')->get('bulk'))->flatten()->implode(' | ');
    expect($errors)->toContain('unknown SKU NOPE-1')->toContain('invalid SKU or quantity')->toContain('Minimum order quantity');
    expect(\App\Models\CartItem::count())->toBe(2);
});

it('rejects CSV files of the wrong type', function () {
    $this->actingAs(wholesaleUser())->post('/wholesale/bulk-order', ['csv' => UploadedFile::fake()->create('x.php', 1)])->assertSessionHasErrors('csv');
});

// ---- Quotes ----
function requestQuote($test, User $u, $variant): Quote
{
    $test->actingAs($u)->post('/wholesale/quotes', ['items' => [['sku' => $variant->sku, 'quantity' => 200, 'target_price' => 600]], 'notes' => 'Need best price'])->assertRedirect();

    return Quote::latest('id')->first();
}

function adminQuote(Quote $q, string $price, $expires = null): void
{
    $q->items()->update(['quoted_price' => $price]);
    $q->forceFill(['status' => 'quoted', 'expires_at' => $expires ?? now()->addWeek(), 'quoted_total' => bcmul($price, '200', 2)])->save();
}

it('runs the full quote lifecycle and converts to an order at the agreed price', function () {
    $u = wholesaleUser();
    $q = requestQuote($this, $u, $this->variant);
    expect($q->status)->toBe('requested')->and($q->events)->toHaveCount(1);

    $this->actingAs($u)->post("/wholesale/quotes/{$q->reference}/accept")->assertStatus(422); // not quoted yet
    adminQuote($q, '650.00');

    $this->actingAs($u)->post("/wholesale/quotes/{$q->reference}/accept")->assertRedirect("/wholesale/quotes/{$q->reference}/checkout");
    $payload = checkoutPayload(['shipping_method_id' => \App\Models\ShippingMethod::where('audience', 'wholesale')->value('id'), 'payment_method' => 'bank_transfer']);
    $this->actingAs($u)->post("/wholesale/quotes/{$q->reference}/checkout", $payload)->assertRedirect();

    $order = Order::first();
    expect($order->subtotal)->toBe('130000.00')->and($order->items->first()->unit_price)->toBe('650.00')
        ->and($order->items->first()->price_source)->toBe('quote')->and($order->quote_id)->toBe($q->id)
        ->and($q->fresh()->status)->toBe('converted');

    // Cannot convert the same quote twice.
    $this->actingAs($u)->get("/wholesale/quotes/{$q->reference}/checkout")->assertForbidden();
});

it('protects quote prices from price tampering at checkout', function () {
    $u = wholesaleUser();
    $q = requestQuote($this, $u, $this->variant);
    adminQuote($q, '650.00');
    $q->update(['status' => 'accepted']);
    $payload = checkoutPayload(['shipping_method_id' => \App\Models\ShippingMethod::where('audience', 'wholesale')->value('id'), 'payment_method' => 'bank_transfer', 'unit_price' => 1, 'items' => [['price' => 1]]]);
    $this->actingAs($u)->post("/wholesale/quotes/{$q->reference}/checkout", $payload);
    expect(Order::first()->items->first()->unit_price)->toBe('650.00');
});

it('does not let one customer see, accept or check out another customer\'s quote', function () {
    $q = requestQuote($this, wholesaleUser(), $this->variant);
    adminQuote($q, '650.00');
    $q->update(['status' => 'accepted']);
    $intruder = wholesaleUser();

    $this->actingAs($intruder)->get("/wholesale/quotes/{$q->reference}")->assertNotFound();
    $this->actingAs($intruder)->post("/wholesale/quotes/{$q->reference}/accept")->assertNotFound();
    $this->actingAs($intruder)->get("/wholesale/quotes/{$q->reference}/checkout")->assertForbidden();
});

it('expires quotes and blocks acceptance and checkout after expiry', function () {
    $u = wholesaleUser();
    $q = requestQuote($this, $u, $this->variant);
    adminQuote($q, '650.00', now()->subMinute());

    $this->actingAs($u)->post("/wholesale/quotes/{$q->reference}/accept")->assertStatus(422);
    expect($q->fresh()->status)->toBe('expired');

    $q2 = requestQuote($this, $u, $this->variant);
    adminQuote($q2, '650.00');
    $q2->update(['status' => 'accepted', 'expires_at' => now()->subMinute()]);
    $this->actingAs($u)->get("/wholesale/quotes/{$q2->reference}/checkout")->assertForbidden();
});

it('only quotes active wholesale-enabled products', function () {
    $retailOnly = makeVariant(['wholesale_enabled' => false]);
    $this->actingAs(wholesaleUser())->post('/wholesale/quotes', ['items' => [['sku' => $retailOnly->sku, 'quantity' => 10]]])->assertSessionHasErrors('items');
    expect(Quote::count())->toBe(0);
});
