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
 * (database/seed-assets/catalog.json + images/): real product names, brands, PKR prices, colour options and images.
 *
 * Source: PriceOye listings. Prices change often: re-run the scraper or edit in Admin > Products before selling.
 * Images are the listings' product photos: confirm you have the right to use them on your store before launch
 * (each image row keeps its source_url).
 *
 * Deliberately NOT invented: sale prices, ratings, sales counts, warranty/authenticity claims, specifications,
 * wholesale tiers, cost prices. Opening stock is unknown, so every variant starts at OPENING_STOCK as a
 * placeholder: set the real counts with "Adjust stock" in the admin.
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
            $brandName = $this->brandName($row['brand'] ?? null);
            $brand = $brandName ? Brand::firstOrCreate(['slug' => Str::slug($brandName)], ['name' => $brandName]) : null;
            $slug = Str::slug($row['name']);
            if (Product::where('slug', $slug)->exists()) {
                continue; // idempotent
            }

            $colors = $row['colors'] ?? [];
            $specs = array_filter(['Brand' => $brandName, 'Available colours' => $colors ? implode(', ', $colors) : null]);

            $product = Product::create([
                'name' => $row['name'], 'slug' => $slug, 'brand_id' => $brand?->id, 'category_id' => $cats[$row['category']]->id,
                'status' => 'active', 'manufacturer' => $brandName,
                'short_description' => $row['name'],
                'description' => $row['name'].($brandName ? "\n\nBrand: {$brandName}" : '').($colors ? "\nAvailable colours: ".implode(', ', $colors) : '')
                    ."\n\nListing details collected from the Pakistani market. Detailed specifications are not published for this item yet.",
                'specifications' => $specs ?: null,
                'compatibility_note' => $this->portNote($row),
                'wholesale_enabled' => true, 'published_at' => now(),
            ]);

            // One variant per real colour option (or a single default variant).
            foreach ($colors ?: [null] as $i => $color) {
                $base = Str::upper(Str::limit(Str::slug($row['name']), 55, ''));
                $sku = $color ? $base.'-'.Str::upper(Str::slug($color)) : $base;
                $n = 1;
                while (isset($usedSkus[$sku])) {
                    $sku = ($color ? $base.'-'.Str::upper(Str::slug($color)) : $base).'-'.(++$n);
                }
                $usedSkus[$sku] = true;

                $product->variants()->create([
                    'sku' => $sku, 'name' => $color, 'color' => $color, 'price' => $row['price'],
                    'stock_quantity' => self::OPENING_STOCK, 'low_stock_threshold' => 5, 'wholesale_moq' => 1,
                    'is_default' => $i === 0, 'sort_order' => $i,
                ]);
            }

            if (! empty($row['image_file']) && File::exists($src = database_path('seed-assets/images/'.$row['image_file']))) {
                $path = 'products/'.$row['image_file'];
                $disk->put($path, File::get($src));
                $product->images()->create(['path' => $path, 'alt' => $row['name'], 'source_url' => $row['page']]);
            }

            if ($port = $this->port($row)) {
                $product->deviceModels()->syncWithoutDetaching($byPort[$port]->all());
            }
        }
    }

    private function brandName(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || in_array(strtolower($raw), ['assorted', 'generic', 'unknown'], true)) {
            return null;
        }
        // "URPIN" -> "Urpin", keep short acronyms (QCY, LDNIO, WIWU) upper-case only if <= 4 chars.
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
