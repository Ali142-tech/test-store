<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature', 'Unit');

/** A product with one wholesale-enabled variant and 10/50/100 tiers (900/800/700 vs retail 1000). */
function makeVariant(array $product = [], array $variant = []): ProductVariant
{
    $p = Product::create(array_merge(['name' => 'Case '.uniqid(), 'slug' => 'case-'.uniqid(), 'status' => 'active', 'wholesale_enabled' => true], $product));
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

/** Retail 250 flat (free over 5000), wholesale freight 500 (free over 100000), settings for payments. */
function seedShipping(): ShippingZone
{
    $zone = ShippingZone::create(['name' => 'PK']);
    $zone->methods()->create(['name' => 'Standard', 'audience' => 'retail', 'base_rate' => 250, 'free_over' => 5000]);
    $zone->methods()->create(['name' => 'Freight', 'audience' => 'wholesale', 'base_rate' => 500, 'free_over' => 100000]);
    foreach (['payment_cod_enabled' => '1', 'payment_bank_enabled' => '1', 'bank_account_number' => 'PK00TEST0000000000000001', 'bank_name' => 'Demo Bank', 'payment_cod_wholesale' => '0', 'wholesale_min_order_value' => '0'] as $k => $v) {
        StoreSetting::updateOrCreate(['key' => $k], ['value' => $v]);
    }

    return $zone;
}

function checkoutPayload(array $overrides = []): array
{
    $addr = ['full_name' => 'Ali Khan', 'phone' => '03001234567', 'line1' => '1 Mall Road', 'city' => 'Lahore', 'country' => 'PK'];

    return array_replace_recursive([
        'email' => 'buyer@example.com', 'phone' => '03001234567', 'shipping' => $addr, 'billing_same' => 1,
        'shipping_method_id' => \App\Models\ShippingMethod::where('audience', 'retail')->value('id'),
        'payment_method' => 'cod', 'terms' => 1, 'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
    ], $overrides);
}

function staff(string $role): User
{
    $u = User::factory()->create();
    $u->assignRole($role);

    return $u->fresh();
}
