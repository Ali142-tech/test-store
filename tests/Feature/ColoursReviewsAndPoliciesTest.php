<?php

use App\Models\Page;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\StoreSetting;
use Database\Seeders\PolicyPagesSeeder;

function colourProduct(): Product
{
    $p = Product::create(['name' => 'Palette Case', 'slug' => 'palette-case', 'status' => 'active']);
    $black = $p->variants()->create(['sku' => 'PAL-BLK', 'name' => 'Black', 'color' => 'Black', 'price' => 1000, 'stock_quantity' => 5, 'is_default' => true]);
    $blue = $p->variants()->create(['sku' => 'PAL-BLU', 'name' => 'Sky Blue', 'color' => 'Sky Blue', 'price' => 1200, 'stock_quantity' => 5, 'sort_order' => 1]);
    $p->variants()->create(['sku' => 'PAL-RED', 'name' => 'Red', 'color' => 'Red', 'price' => 1000, 'stock_quantity' => 0, 'sort_order' => 2]);
    ProductImage::create(['product_id' => $p->id, 'product_variant_id' => $black->id, 'path' => 'products/black.webp', 'alt' => 'Black', 'sort_order' => 0]);
    ProductImage::create(['product_id' => $p->id, 'product_variant_id' => $blue->id, 'path' => 'products/blue.webp', 'alt' => 'Blue', 'sort_order' => 1]);

    return $p;
}

it('gives every colour its own gallery, price and swatch on the product page', function () {
    $res = $this->get(route('product', colourProduct()))->assertOk();
    $variants = $res->viewData('variants');

    expect($variants->pluck('name')->all())->toBe(['Black', 'Sky Blue', 'Red'])
        ->and($variants[0]['images'][0]['url'])->toContain('black.webp')
        ->and($variants[1]['images'][0]['url'])->toContain('blue.webp')
        ->and($variants[1]['price'])->toBe('1200.00')
        ->and($variants[0]['swatch'])->not->toBeNull()
        ->and($variants[2]['available'])->toBe(0);
    $res->assertSee('Colour', false);
});

it('shows colour dots on product cards for multi-colour products only', function () {
    colourProduct();
    $single = Product::create(['name' => 'Solo Cable', 'slug' => 'solo-cable', 'status' => 'active']);
    $single->variants()->create(['sku' => 'SOLO', 'price' => 500, 'stock_quantity' => 3, 'is_default' => true, 'color' => 'Black']);

    $html = $this->get('/shop')->getContent();
    expect($html)->toContain('title="Sky Blue"')->toContain('3 colours')->not->toContain('1 colours');
});

it('is not marked out of stock when only some colours are sold out', function () {
    $p = colourProduct();
    $p->variants()->where('sku', 'PAL-BLK')->update(['stock_quantity' => 0]); // default colour sold out, blue still available
    $this->get('/shop')->assertSee('Palette Case')->assertDontSee('Out of stock');
});

it('never shows reviews or links from other stores', function () {
    $html = $this->get(route('product', colourProduct()))->assertOk()->getContent();
    expect($html)->not->toContain('other Pakistani stores')->not->toContain('priceoye')->not->toContain('PriceOye')->not->toContain('aggregateRating');
    foreach (['/', '/shop'] as $u) {
        expect($this->get($u)->getContent())->not->toContain('priceoye');
    }
});

it('seeds complete policy pages that render with live store details', function () {
    StoreSetting::updateOrCreate(['key' => 'store_name'], ['value' => 'Acme Mobile']);
    StoreSetting::updateOrCreate(['key' => 'contact_email'], ['value' => 'help@acme.test']);
    $this->seed(PolicyPagesSeeder::class);

    foreach (['about', 'shipping-policy', 'returns-and-refunds', 'privacy-policy', 'terms-and-conditions', 'warranty-policy'] as $slug) {
        $html = $this->get("/pages/{$slug}")->assertOk()->getContent();
        expect($html)->not->toContain('{store_name}')->not->toContain('{contact_link}')->not->toContain('Placeholder text');
    }
    $this->get('/pages/privacy-policy')->assertSee('Acme Mobile')->assertSee('help@acme.test')->assertSee('collect or store card numbers')->assertSee('Cookies');
    $this->get('/pages/returns-and-refunds')->assertSee('7 days of delivery');
});

it('falls back to the contact page when no contact email is configured, and keeps admin edits', function () {
    $this->seed(PolicyPagesSeeder::class);
    $this->get('/pages/shipping-policy')->assertSee('our contact page');

    Page::where('slug', 'about')->update(['body' => '<p>Custom about text</p>']);
    $this->seed(PolicyPagesSeeder::class);
    expect(Page::where('slug', 'about')->value('body'))->toBe('<p>Custom about text</p>');
});

it('offers the review form on the product page only to customers who received the product', function () {
    seedShipping();
    $v = makeVariant(['wholesale_enabled' => false]);
    $url = route('product', $v->product);

    $this->get($url)->assertSee('Only customers who have received this product can review it')->assertDontSee('Submit review');

    $u = \App\Models\User::factory()->create();
    $this->actingAs($u)->post('/cart/add', ['variant_id' => $v->id, 'quantity' => 1]);
    $this->actingAs($u)->post('/checkout', checkoutPayload());
    $this->actingAs($u)->get($url)->assertDontSee('Submit review'); // ordered, not delivered yet

    $order = \App\Models\Order::first();
    foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $s) {
        app(\App\Services\Orders\OrderService::class)->transition($order->fresh(), $s);
    }
    $this->actingAs($u)->get($url)->assertSee('Write a review')->assertSee('Submit review');
    $this->actingAs($u)->post("/account/reviews/{$v->product->slug}", ['rating' => 4, 'title' => 'Solid', 'body' => 'Works well'])->assertSessionHasNoErrors();

    $this->actingAs($u)->get($url)->assertSee('awaiting approval')->assertSee('Update your review');
    $this->get($url)->assertDontSee('Works well'); // pending reviews are invisible to everyone else
    \App\Models\Review::first()->forceFill(['status' => 'approved'])->save();
    $this->get($url)->assertSee('Works well')->assertSee('Verified purchase');
});
