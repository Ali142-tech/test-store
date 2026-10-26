<?php

use App\Models\Product;
use App\Models\User;
use App\Models\WholesalePrice;
use App\Services\Pricing\PriceResolver;

function makeVariant(array $product = [], array $variant = []): App\Models\ProductVariant
{
    $p = Product::create(array_merge(['name' => 'Case', 'slug' => 'case-'.uniqid(), 'status' => 'active', 'wholesale_enabled' => true], $product));
    $v = $p->variants()->create(array_merge(['sku' => 'SKU-'.uniqid(), 'price' => 1000, 'stock_quantity' => 500, 'wholesale_moq' => 10, 'is_default' => true], $variant));
    foreach ([[10, 49, 900], [50, 99, 800], [100, null, 700]] as [$min, $max, $price]) {
        $v->tiers()->create(['min_quantity' => $min, 'max_quantity' => $max, 'price' => $price]);
    }

    return $v->fresh();
}

function wholesaleUser(string $status = 'approved'): User
{
    $u = User::factory()->create();
    $u->forceFill(['customer_type' => 'wholesale'])->save();
    $u->wholesaleApplication()->create([
        'business_name' => 'Shop', 'contact_name' => 'A', 'contact_phone' => '1', 'business_address' => 'x', 'city' => 'Lahore',
    ]);
    $u->wholesaleApplication->forceFill(['status' => $status])->save();

    return $u->fresh();
}

it('charges retail price to guests and retail customers regardless of quantity', function () {
    $v = makeVariant();
    $r = new PriceResolver;

    expect($r->resolve($v, 100, null))->toMatchArray(['price' => '1000.00', 'source' => 'regular'])
        ->and($r->resolve($v, 100, User::factory()->create()))->toMatchArray(['price' => '1000.00']);
});

it('applies quantity tiers for approved wholesale users', function (int $qty, string $expected) {
    $v = makeVariant();

    expect((new PriceResolver)->resolve($v, $qty, wholesaleUser())['price'])->toBe($expected);
})->with([[1, '1000.00'], [9, '1000.00'], [10, '900.00'], [49, '900.00'], [50, '800.00'], [100, '700.00'], [500, '700.00']]);

it('denies wholesale prices to pending or rejected wholesale applicants', function (string $status) {
    $v = makeVariant();

    expect((new PriceResolver)->resolve($v, 100, wholesaleUser($status))['price'])->toBe('1000.00');
})->with(['pending', 'rejected']);

it('does not apply tiers to products that are not wholesale enabled', function () {
    $v = makeVariant(['wholesale_enabled' => false]);

    expect((new PriceResolver)->resolve($v, 100, wholesaleUser())['price'])->toBe('1000.00');
});

it('prefers a negotiated price over tiers and ignores expired ones', function () {
    $v = makeVariant();
    $u = wholesaleUser();
    $r = new PriceResolver;

    WholesalePrice::create(['user_id' => $u->id, 'product_variant_id' => $v->id, 'price' => 650, 'expires_at' => now()->addDay()]);
    expect($r->resolve($v, 10, $u))->toMatchArray(['price' => '650.00', 'source' => 'negotiated']);

    WholesalePrice::where('user_id', $u->id)->update(['expires_at' => now()->subDay()]);
    expect($r->resolve($v, 10, $u))->toMatchArray(['price' => '900.00', 'source' => 'tier']);
});

it('uses the sale price only inside its schedule', function () {
    $v = makeVariant([], ['sale_price' => 800, 'sale_starts_at' => now()->subDay(), 'sale_ends_at' => now()->addDay()]);
    expect((new PriceResolver)->resolve($v, 1)['price'])->toBe('800.00');

    $v->update(['sale_ends_at' => now()->subHour()]);
    expect((new PriceResolver)->resolve($v->fresh(), 1)['price'])->toBe('1000.00');
});

it('enforces minimum order quantity only for approved wholesale buyers', function () {
    $v = makeVariant();
    $r = new PriceResolver;

    expect($r->minimumQuantity($v, wholesaleUser()))->toBe(10)
        ->and($r->minimumQuantity($v, User::factory()->create()))->toBe(1)
        ->and($r->minimumQuantity($v, null))->toBe(1);
});

it('never exposes tier tables to non-wholesale users', function () {
    $v = makeVariant();
    $r = new PriceResolver;

    expect($r->visibleTiers($v, null))->toBeEmpty()
        ->and($r->visibleTiers($v, User::factory()->create()))->toBeEmpty()
        ->and($r->visibleTiers($v, wholesaleUser()))->toHaveCount(3);
});

it('cannot self-promote to wholesale through mass assignment', function () {
    $u = User::create(['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret123', 'customer_type' => 'wholesale', 'is_active' => false]);

    expect($u->fresh()->customer_type)->toBe('retail')->and($u->fresh()->is_active)->toBeTrue();
});
