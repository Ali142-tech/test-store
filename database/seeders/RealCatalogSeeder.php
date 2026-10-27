<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeds the REAL Pakistani market catalogue collected by `php artisan catalog:scrape`
 * (database/seed-assets/catalog.json + images/): product names, brands, PKR prices per colour, the specifications
 * published for each product, per-colour photo galleries, and per-colour availability. Reviews from other sites are NOT imported.
 *
 * Source: Qeemat (market reference prices). Prices change often: re-run the scraper or edit in Admin > Products before selling.
* Images belong to their owners: confirm you may use them on a live store (source_url is kept).
 *
 * Deliberately NOT invented: sale prices, our own ratings, sales counts, warranty/authenticity claims, wholesale
 * tiers, cost prices. Opening stock is unknown, so in-stock variants start at OPENING_STOCK as a placeholder
 * (variants the source lists as sold out start at 0): set real counts with "Adjust stock" in the admin.
 */
class RealCatalogSeeder extends Seeder
{
    private const OPENING_STOCK = 10;

    private const CATEGORY_ICONS = [
        'Game Consoles' => 'gamepad', 'Controllers & Gamepads' => 'gamepad', 'Gaming Keyboards' => 'keyboard', 'Gaming Mice' => 'mouse',
        'Gaming Headsets & Headphones' => 'headphones', 'Gaming Monitors' => 'monitor', 'VR Headsets' => 'vr',
    ];

    /** platform group => models (used for "shop by platform" compatibility filters); matched by keyword in the product name. */
    private const PLATFORMS = [
        'PlayStation' => [['PlayStation 5', 'playstation|ps5|ps4|dualsense|dualshock'], ['PlayStation VR2', 'vr2']],
        'Xbox' => [['Xbox Series X|S', 'xbox']],
        'Nintendo' => [['Nintendo Switch', 'switch|nintendo']],
        'PC & Handheld' => [['Windows PC', 'monitor|keyboard|mouse|gk[0-9]|superlight|razer|logitech|hyperx|steelseries|bloody|glorious|attack shark|skyloong|wireless gaming|xinput|usb'], ['Steam Deck', 'steam deck']],
        'Meta Quest' => [['Meta Quest', 'quest']],
    ];

    public function run(): void
    {
        $file = database_path('seed-assets/catalog.json');
        if (! File::exists($file)) {
            $this->command?->warn('database/seed-assets/catalog.json missing: run `php artisan catalog:scrape` first.');

            return;
        }

        $cats = [];
        foreach (array_keys(self::CATEGORY_ICONS) as $i => $name) {
            $cats[$name] = Category::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'icon' => self::CATEGORY_ICONS[$name], 'sort_order' => $i]);
        }

        $platforms = [];
        foreach (self::PLATFORMS as $brand => $models) {
            $db = DeviceBrand::firstOrCreate(['slug' => Str::slug($brand)], ['name' => $brand]);
            foreach ($models as [$m, $pattern]) {
                $platforms[] = [DeviceModel::firstOrCreate(['device_brand_id' => $db->id, 'slug' => Str::slug($m)], ['name' => $m])->id, $pattern];
            }
        }

        $disk = Storage::disk(config('store.media_disk'));
        $usedSkus = [];

        foreach (json_decode(File::get($file), true) as $row) {
            $slug = Str::slug($row['name']);
            if (Product::where('slug', $slug)->exists()) {
                continue; // idempotent
            }

            $brandName = $this->brandName($row['brand'] ?? null);
            $brand = $brandName ? Brand::firstOrCreate(['slug' => Str::slug($brandName)], ['name' => $brandName]) : null;
            $colors = array_values(array_filter(array_column($row['colors'], 'name')));
            $specs = array_filter(['Brand' => $brandName] + ($row['specs'] ?? []) + ($colors ? ['Available colours' => implode(', ', $colors)] : []));

            $product = Product::create([
                'name' => $row['name'], 'slug' => $slug, 'brand_id' => $brand?->id, 'category_id' => $cats[$row['category']]->id,
                'status' => 'active', 'manufacturer' => $brandName, 'short_description' => $this->summary($row, $brandName),
                'description' => $this->description($row, $brandName, $colors),
                'specifications' => $specs ?: null,
                'compatibility_note' => null,
                'wholesale_enabled' => true, 'published_at' => now(),
            ]);

            // Default colour first: its photos become the product's primary images.
            $order = 0;
            foreach ($row['colors'] as $i => $c) {
                $color = $c['name'];
                $base = Str::upper(Str::limit(Str::slug($row['name']), 55, ''));
                $sku = $color ? $base.'-'.Str::upper(Str::slug($color)) : $base;
                for ($n = 1; isset($usedSkus[$sku]); $n++) {
                    $sku = ($color ? $base.'-'.Str::upper(Str::slug($color)) : $base).'-'.($n + 1);
                }
                $usedSkus[$sku] = true;

                $variant = $product->variants()->create([
                    'sku' => $sku, 'name' => $color, 'color' => $color, 'price' => $c['price'],
                    'stock_quantity' => $c['available'] ? self::OPENING_STOCK : 0, 'low_stock_threshold' => 5, 'wholesale_moq' => 1,
                    'is_default' => $i === 0, 'sort_order' => $i,
                ]);

                foreach ($c['images'] as $file) {
                    $this->attach($product, $variant->id, $file, $color ? "{$row['name']} - $color" : $row['name'], $row['page'], $disk, $order++);
                }
            }
            if (! empty($row['fallback_image'])) {
                $this->attach($product, null, $row['fallback_image'], $row['name'], $row['page'], $disk, $order++);
            }

            $ids = collect($platforms)->filter(fn ($p) => preg_match('/'.$p[1].'/i', $row['name']))->pluck(0)->all();
            if ($ids) {
                $product->deviceModels()->syncWithoutDetaching($ids);
            }

        }
    }

    private function attach(Product $product, ?int $variantId, string $file, string $alt, string $source, $disk, int $order): void
    {
        $src = database_path('seed-assets/images/'.$file);
        if (! File::exists($src)) {
            return;
        }
        $disk->put('products/'.$file, File::get($src));
        $product->images()->create(['product_variant_id' => $variantId, 'path' => 'products/'.$file, 'alt' => $alt, 'source_url' => $source, 'sort_order' => $order]);
    }

    private function summary(array $row, ?string $brand): string
    {
        $bits = array_filter([$brand, $row['specs']['General Information · Model'] ?? null]);

        return $row['name'].($bits ? ' ('.implode(' · ', $bits).')' : '');
    }

    private function description(array $row, ?string $brand, array $colors): string
    {
        $lines = [$row['name'], ''];
        if ($brand) {
            $lines[] = "Brand: {$brand}";
        }
        if ($colors) {
            $lines[] = 'Available colours: '.implode(', ', $colors);
        }
        if (empty($row['specs'])) {
            $lines[] = '';
            $lines[] = 'Detailed specifications are not published for this item yet.';
        }

        return implode("\n", $lines);
    }

    private function brandName(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || in_array(strtolower($raw), ['assorted', 'generic', 'unknown'], true)) {
            return null;
        }

        // "URPIN" -> "Urpin"; short acronyms (QCY, LDNIO, WIWU) stay upper-case.
        return (strtoupper($raw) === $raw && strlen($raw) > 4) ? Str::title(strtolower($raw)) : $raw;
    }
}
