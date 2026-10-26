<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** DEMO DATA ONLY. Brands are fictional so no authenticity claims are implied. */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $brands = collect(['Voltix Essentials', 'Aegis', 'Nova Audio', 'Linkr', 'Gripwell'])
            ->mapWithKeys(fn ($n, $i) => [$n => Brand::firstOrCreate(['slug' => Str::slug($n)], ['name' => $n, 'sort_order' => $i])]);

        $cats = collect([
            ['Cases & Covers', 'shield'], ['Screen Protectors', 'layers'], ['Chargers & Adapters', 'zap'],
            ['Cables', 'cable'], ['Power Banks', 'battery'], ['Wireless Chargers', 'wifi'],
            ['Earbuds & Headphones', 'headphones'], ['Car Accessories', 'car'], ['Stands & Grips', 'stand'],
            ['Lens Protectors', 'camera'], ['Smartwatch Accessories', 'watch'], ['Bundles & Packs', 'package'],
        ])->mapWithKeys(fn ($c, $i) => [$c[0] => Category::firstOrCreate(['slug' => Str::slug($c[0])], ['name' => $c[0], 'icon' => $c[1], 'sort_order' => $i])]);

        $devices = [
            'Apple iPhone' => ['iPhone 16 Pro Max', 'iPhone 16 Pro', 'iPhone 16', 'iPhone 15', 'iPhone 14'],
            'Samsung Galaxy' => ['Galaxy S25 Ultra', 'Galaxy S25', 'Galaxy S24', 'Galaxy A55'],
            'Google Pixel' => ['Pixel 9 Pro', 'Pixel 9', 'Pixel 8a'],
            'Xiaomi & Redmi' => ['Xiaomi 14', 'Redmi Note 13 Pro'],
            'Oppo & Vivo' => ['Oppo Reno 12', 'Vivo V40'],
            'OnePlus' => ['OnePlus 12', 'OnePlus Nord 4'],
        ];
        $models = collect();
        foreach ($devices as $brand => $list) {
            $db = DeviceBrand::firstOrCreate(['slug' => Str::slug($brand)], ['name' => $brand]);
            foreach ($list as $m) {
                $models->push(DeviceModel::firstOrCreate(['device_brand_id' => $db->id, 'slug' => Str::slug($m)], ['name' => $m]));
            }
        }

        // [name, category, brand, short description, price, variants[name=>sku-suffix], wholesale tiers, compatible?]
        $defs = [
            ['Armor Shield Case', 'Cases & Covers', 'Aegis', 'Shock-absorbing protective case with raised camera edge.', 1800, ['Black', 'Blue', 'Clear'], true, true],
            ['SlimFit Clear Case', 'Cases & Covers', 'Voltix Essentials', 'Ultra-thin transparent case that shows off your phone.', 1200, ['Clear'], true, true],
            ['Tempered Glass 9H', 'Screen Protectors', 'Aegis', '9H tempered glass screen protector, bubble-free install tray included.', 900, ['Single', '3-Pack'], true, true],
            ['Camera Lens Guard', 'Lens Protectors', 'Aegis', 'Individual lens rings that protect without affecting photos.', 1100, ['Standard'], false, true],
            ['GaN 65W Fast Charger', 'Chargers & Adapters', 'Linkr', 'Compact 65W USB-C charger for phones, tablets and laptops.', 5200, ['65W'], true, false],
            ['20W USB-C Charger', 'Chargers & Adapters', 'Linkr', 'Fast 20W USB-C wall adapter.', 2200, ['20W'], true, false],
            ['USB-C to USB-C Cable', 'Cables', 'Linkr', 'Braided 60W USB-C cable.', 1400, ['1m', '2m'], true, false],
            ['USB-C to Lightning Cable', 'Cables', 'Linkr', 'Braided USB-C to Lightning cable.', 1600, ['1m', '2m'], true, false],
            ['PowerCore 10000 Power Bank', 'Power Banks', 'Voltix Essentials', '10,000 mAh pocket power bank with USB-C.', 6500, ['Black', 'White'], true, false],
            ['MagFlow Wireless Charger', 'Wireless Chargers', 'Voltix Essentials', '15W magnetic wireless charging pad.', 4800, ['Black'], true, false],
            ['Air Buds Lite', 'Earbuds & Headphones', 'Nova Audio', 'True wireless earbuds with charging case.', 7900, ['White', 'Black'], true, false],
            ['Dash Car Charger', 'Car Accessories', 'Linkr', 'Dual-port 48W car charger.', 2500, ['Dual Port'], true, false],
            ['Vent Phone Holder', 'Car Accessories', 'Gripwell', 'Adjustable air-vent phone mount.', 1500, ['Black'], true, false],
            ['FlexGrip Phone Stand', 'Stands & Grips', 'Gripwell', 'Foldable desktop phone stand.', 800, ['Black', 'Silver'], true, false],
            ['Watch Band Silicone', 'Smartwatch Accessories', 'Voltix Essentials', 'Soft silicone replacement band.', 1300, ['S', 'M', 'L'], true, false],
        ];

        $n = 0;
        foreach ($defs as [$name, $cat, $brand, $short, $price, $variants, $wholesale, $compat]) {
            $n++;
            $product = Product::firstOrCreate(['slug' => Str::slug($name)], [
                'name' => $name, 'category_id' => $cats[$cat]->id, 'brand_id' => $brands[$brand]->id,
                'short_description' => $short, 'description' => $short.' (Demo product description - replace with real copy.)',
                'manufacturer' => $brand, 'warranty' => '6 months seller warranty (demo)', 'status' => 'active',
                'is_featured' => $n % 3 === 0, 'is_best_seller' => $n % 4 === 0, 'wholesale_enabled' => $wholesale,
                'sales_count' => random_int(0, 200), 'published_at' => now()->subDays(random_int(1, 60)),
                'specifications' => ['Material' => 'Demo', 'Origin' => 'Demo'],
            ]);

            foreach ($variants as $i => $vName) {
                $variant = $product->variants()->firstOrCreate(
                    ['sku' => strtoupper(Str::slug($name)).'-'.strtoupper(Str::slug($vName))],
                    [
                        'name' => $vName, 'color' => in_array($vName, ['Black', 'Blue', 'White', 'Clear', 'Silver']) ? $vName : null,
                        'price' => $price + ($vName === '2m' ? 400 : 0) + ($vName === '3-Pack' ? $price * 1.6 : 0),
                        'sale_price' => $n % 5 === 0 ? round($price * 0.85) : null,
                        'cost_price' => round($price * 0.55), 'stock_quantity' => random_int(0, 150),
                        'low_stock_threshold' => 10, 'wholesale_moq' => $wholesale ? 10 : 1,
                        'is_default' => $i === 0, 'sort_order' => $i,
                    ]
                );
                if ($wholesale) {
                    foreach ([[10, 49, .88], [50, 99, .80], [100, null, .72]] as [$min, $max, $factor]) {
                        $variant->tiers()->firstOrCreate(['min_quantity' => $min], ['max_quantity' => $max, 'price' => round($variant->price * $factor)]);
                    }
                }
            }

            if ($compat) {
                $product->deviceModels()->syncWithoutDetaching($models->random(min(5, $models->count()))->pluck('id'));
            }
        }

        // Demo bundle: stock derives from its components.
        $bundle = Product::firstOrCreate(['slug' => 'protect-and-charge-bundle'], [
            'name' => 'Protect & Charge Bundle', 'type' => 'bundle', 'category_id' => $cats['Bundles & Packs']->id, 'brand_id' => $brands['Voltix Essentials']->id,
            'short_description' => 'Case, tempered glass and a 20W charger in one pack.', 'status' => 'active', 'published_at' => now(),
        ]);
        $parts = \App\Models\ProductVariant::whereIn('sku', ['ARMOR-SHIELD-CASE-BLACK', 'TEMPERED-GLASS-9H-SINGLE', '20W-USB-C-CHARGER-20W'])->get();
        if ($bundle->variants()->count() === 0 && $parts->count() === 3) {
            $bundle->variants()->create(['sku' => 'BUNDLE-PROTECT-CHARGE', 'price' => round($parts->sum('price') * 0.9), 'is_default' => true]);
            foreach ($parts as $p) {
                \App\Models\BundleItem::create(['bundle_product_id' => $bundle->id, 'product_variant_id' => $p->id, 'quantity' => 1]);
            }
        }
    }
}
