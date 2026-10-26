<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use App\Models\ExternalReview;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeds the REAL Pakistani market catalogue collected by `php artisan catalog:scrape`
 * (database/seed-assets/catalog.json + images/): product names, brands, PKR prices per colour, the specifications
 * published for each product, per-colour photo galleries, per-colour availability, and the public reviews shown for
 * the product on the source site (stored as clearly attributed EXTERNAL reviews, never as this store's own).
 *
 * Source: PriceOye. Prices change often: re-run the scraper or edit in Admin > Products before selling.
 * Images/reviews belong to their owners: confirm you may use them on a live store (source_url is kept).
 *
 * Deliberately NOT invented: sale prices, our own ratings, sales counts, warranty/authenticity claims, wholesale
 * tiers, cost prices. Opening stock is unknown, so in-stock variants start at OPENING_STOCK as a placeholder
 * (variants the source lists as sold out start at 0): set real counts with "Adjust stock" in the admin.
 */
class RealCatalogSeeder extends Seeder
{
    private const OPENING_STOCK = 25;

    private const CATEGORY_ICONS = [
        'Cases & Covers' => 'shield', 'Screen Protectors' => 'layers', 'Chargers & Adapters' => 'zap', 'Cables' => 'cable',
        'Power Banks' => 'battery', 'Wireless Chargers' => 'wifi', 'Earbuds & Headphones' => 'headphones', 'Car Accessories' => 'car',
        'Stands & Grips' => 'stand', 'Lens Protectors' => 'camera', 'Smartwatch Accessories' => 'watch', 'Bundles & Packs' => 'package',
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

        // Real phone models used for compatibility filters (USB-C except the iPhone 14 generation).
        $devices = [
            'Apple iPhone' => [['iPhone 16 Pro Max', 'usbc'], ['iPhone 16 Pro', 'usbc'], ['iPhone 16', 'usbc'], ['iPhone 15', 'usbc'], ['iPhone 14', 'lightning']],
            'Samsung Galaxy' => [['Galaxy S25 Ultra', 'usbc'], ['Galaxy S25', 'usbc'], ['Galaxy S24', 'usbc'], ['Galaxy A55', 'usbc']],
            'Google Pixel' => [['Pixel 9 Pro', 'usbc'], ['Pixel 9', 'usbc'], ['Pixel 8a', 'usbc']],
            'Xiaomi & Redmi' => [['Xiaomi 14', 'usbc'], ['Redmi Note 13 Pro', 'usbc']],
            'Oppo & Vivo' => [['Oppo Reno 12', 'usbc'], ['Vivo V40', 'usbc']],
            'OnePlus' => [['OnePlus 12', 'usbc'], ['OnePlus Nord 4', 'usbc']],
        ];
        $byPort = ['usbc' => collect(), 'lightning' => collect()];
        foreach ($devices as $brand => $models) {
            $db = DeviceBrand::firstOrCreate(['slug' => Str::slug($brand)], ['name' => $brand]);
            foreach ($models as [$m, $port]) {
                $byPort[$port]->push(DeviceModel::firstOrCreate(['device_brand_id' => $db->id, 'slug' => Str::slug($m)], ['name' => $m])->id);
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
                'compatibility_note' => $this->portNote($row),
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

            if ($port = $this->port($row)) {
                $product->deviceModels()->syncWithoutDetaching($byPort[$port]->all());
            }

            foreach ($row['reviews'] ?? [] as $rv) {
                ExternalReview::create([
                    'product_id' => $product->id, 'source' => 'PriceOye', 'source_url' => $row['page'], 'reviewer_name' => $rv['name'] ?: 'Customer',
                    'rating' => max(1, min(5, (int) $rv['rating'])), 'body' => $rv['text'], 'reviewed_on' => $rv['date'] ?: null,
                ]);
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
        $bits = array_filter([$brand, $row['specs']['Battery · Capacity'] ?? null, $row['specs']['Power · Output'] ?? null]);

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

    /** Port only when the listing itself says so (no guessing). */
    private function port(array $row): ?string
    {
        if (! in_array($row['category'], ['Cables', 'Chargers & Adapters'], true)) {
            return null;
        }
        $n = strtolower($row['name']);

        return match (true) {
            str_contains($n, 'lightning') => 'lightning',
            (bool) preg_match('/type-?c|usb-?c|c to c|c2c/', $n) => 'usbc',
            default => null,
        };
    }

    private function portNote(array $row): ?string
    {
        return match ($this->port($row)) {
            'usbc' => 'Listing mentions USB-C / Type-C.',
            'lightning' => 'Listing mentions Lightning.',
            default => null,
        };
    }
}
