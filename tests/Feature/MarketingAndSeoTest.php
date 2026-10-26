<?php

use App\Mail\CartReminderMail;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => seedShipping());

function makeBundle(): array
{
    $a = makeVariant(['wholesale_enabled' => false], ['stock_quantity' => 10]);
    $b = makeVariant(['wholesale_enabled' => false], ['stock_quantity' => 4]);
    $bundle = Product::create(['name' => 'Combo', 'slug' => 'combo', 'type' => 'bundle', 'status' => 'active']);
    $bv = $bundle->variants()->create(['sku' => 'COMBO-1', 'price' => 1500, 'is_default' => true]);
    foreach ([[$a, 1], [$b, 2]] as [$v, $q]) {
        \App\Models\BundleItem::create(['bundle_product_id' => $bundle->id, 'product_variant_id' => $v->id, 'quantity' => $q]);
    }

    return [$bv, $a, $b];
}

it('derives bundle availability from its components', function () {
    [$bv, $a, $b] = makeBundle();
    expect($bv->fresh()->available())->toBe(2); // limited by b: 4 / 2
});

it('deducts component stock when a bundle is sold and restores it on cancellation', function () {
    [$bv, $a, $b] = makeBundle();
    $this->post('/cart/add', ['variant_id' => $bv->id, 'quantity' => 2])->assertSessionHasNoErrors();
    $this->post('/checkout', checkoutPayload());
    $order = Order::first();

    expect($a->fresh()->stock_quantity)->toBe(8)->and($b->fresh()->stock_quantity)->toBe(0)->and($bv->fresh()->available())->toBe(0);
    $this->post('/cart/add', ['variant_id' => $bv->id, 'quantity' => 1])->assertSessionHasErrors('cart'); // sold out

    app(\App\Services\Orders\OrderService::class)->transition($order, 'cancelled');
    expect($a->fresh()->stock_quantity)->toBe(10)->and($b->fresh()->stock_quantity)->toBe(4);
});

it('serves a sitemap listing only public, active pages, and a robots.txt that blocks private areas', function () {
    $live = makeVariant()->product;
    $draft = Product::create(['name' => 'Hidden', 'slug' => 'hidden-draft', 'status' => 'draft']);

    $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->getContent();
    expect($xml)->toContain(route('product', $live))->not->toContain('hidden-draft')->not->toContain('/cart')->not->toContain('/admin');

    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin')->assertSee('Disallow: /checkout')->assertSee('Sitemap:');
});

it('emits product structured data with price but no fake rating, and adds rating only with real reviews', function () {
    $v = makeVariant();
    $html = $this->get(route('product', $v->product))->getContent();
    expect($html)->toContain('"@type":"Product"')->toContain('BreadcrumbList')->toContain('rel="canonical"')->toContain('og:title')
        ->and($html)->not->toContain('aggregateRating');

    Review::create(['product_id' => $v->product_id, 'user_id' => User::factory()->create()->id, 'rating' => 4])->forceFill(['status' => 'approved'])->save();
    expect($this->get(route('product', $v->product))->getContent())->toContain('aggregateRating');
});

it('marks private pages noindex and never caches them publicly', function () {
    $this->get('/cart')->assertSee('noindex', false)->assertHeader('Cache-Control', 'no-store, private');
    $this->get('/login')->assertSee('noindex', false);
    $this->get('/shop')->assertDontSee('name="robots"', false);
});

it('shows a genuine-reviews section only once approved reviews exist', function () {
    $v = makeVariant();
    $this->get('/')->assertDontSee('What customers say');
    Review::create(['product_id' => $v->product_id, 'user_id' => User::factory()->create()->id, 'rating' => 5, 'body' => 'Solid case'])->forceFill(['status' => 'pending'])->save();
    $this->get('/')->assertDontSee('Solid case');
    Review::first()->forceFill(['status' => 'approved'])->save();
    $this->get('/')->assertSee('What customers say')->assertSee('Solid case');
});

it('tracks recently viewed products per session', function () {
    $a = makeVariant()->product;
    $b = makeVariant()->product;
    $this->get(route('product', $a));
    $this->get(route('product', $b))->assertSee('Recently viewed')->assertSee($a->name);
});

it('subscribes to the newsletter once, honours unsubscribe, and blocks bots via honeypot', function () {
    $this->post('/newsletter', ['email' => 'Fan@Example.com'])->assertSessionHasNoErrors();
    $this->post('/newsletter', ['email' => 'fan@example.com']);
    expect(\App\Models\NewsletterSubscriber::count())->toBe(1);

    $sub = \App\Models\NewsletterSubscriber::first();
    $this->get("/newsletter/unsubscribe/{$sub->token}")->assertOk();
    expect($sub->fresh()->unsubscribed_at)->not->toBeNull();

    $this->post('/newsletter', ['email' => 'bot@example.com', 'website' => 'http://spam'])->assertSessionHasErrors('website');
});

it('stores contact messages and flags wholesale leads', function () {
    $this->post('/contact', ['name' => 'A', 'email' => 'a@b.co', 'message' => 'Hello', 'is_wholesale_lead' => 1])->assertSessionHasNoErrors();
    expect(\App\Models\ContactMessage::first()->is_wholesale_lead)->toBeTrue();
    $this->post('/contact', ['name' => '', 'email' => 'bad', 'message' => ''])->assertSessionHasErrors(['name', 'email', 'message']);
});

it('sends abandoned-cart reminders only to consenting, idle, signed-in customers and only once', function () {
    Mail::fake();
    $v = makeVariant();
    $yes = User::factory()->create(['marketing_consent' => true]);
    $no = User::factory()->create(['marketing_consent' => false]);
    foreach ([$yes, $no] as $u) {
        $c = Cart::create(['user_id' => $u->id, 'last_activity_at' => now()->subDays(2)]);
        $c->items()->create(['product_variant_id' => $v->id, 'quantity' => 1]);
    }
    $fresh = User::factory()->create(['marketing_consent' => true]);
    Cart::create(['user_id' => $fresh->id, 'last_activity_at' => now()])->items()->create(['product_variant_id' => $v->id, 'quantity' => 1]);

    $this->artisan('carts:remind')->assertSuccessful();
    $this->artisan('carts:remind')->assertSuccessful();

    Mail::assertQueued(CartReminderMail::class, 1);
    Mail::assertQueued(CartReminderMail::class, fn ($m) => $m->hasTo($yes->email));
});

it('expires overdue quotes via the scheduled command', function () {
    $u = wholesaleUser();
    $q = Quote::create(['reference' => 'QUO-T-1', 'user_id' => $u->id]);
    $q->update(['status' => 'quoted', 'expires_at' => now()->subHour()]);
    $this->artisan('quotes:expire')->assertSuccessful();
    expect($q->fresh()->status)->toBe('expired');
});

it('registers the scheduled jobs', function () {
    $this->artisan('schedule:list')->assertSuccessful()->expectsOutputToContain('quotes:expire');
});
