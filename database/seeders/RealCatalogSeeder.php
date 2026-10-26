<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\BundleItem;
use App\Models\Category;
use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ProductIllustration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Real Pakistani market catalogue.
 *
 * PRICES: PKR retail prices published on PriceOye (priceoye.pk) in October 2026, plus one Qeemat/PriceOye
 *         listing for the Samsung car charger. Retail prices change often: VERIFY AND EDIT before selling
 *         (Admin > Products). Cases, screen protectors, lens protectors, stands/grips and watch bands are
 *         intentionally NOT seeded because no verified Pakistani prices were available.
 * IMAGES: generated illustrations (see ProductIllustration), not product photos. Upload licensed photos in the admin.
 * NOT INVENTED: no sale prices, sales counts, ratings, warranty or authenticity claims, and no cost prices.
 * PLACEHOLDERS (business decisions, edit in admin): opening stock, wholesale tier discounts (5/10/15%) and MOQs.
 */
class RealCatalogSeeder extends Seeder
{
    private const STOCK = 40; // placeholder opening stock; set real counts via Adjust stock

    /** category, brand, name, price PKR, illustration type, spec label, port (usbc|lightning|null) */
    private const PRODUCTS = [
        // ---- Power banks (Anker) ----
        ['Power Banks', 'Anker', 'Anker 533 PowerCore 10000mAh 30W (A1256H11)', 11999, 'powerbank', '10,000 mAh', null],
        ['Power Banks', 'Anker', 'Anker Zolo 10000mAh 35W Power Bank with Built-In Dual USB-C Cables', 10749, 'powerbank', '10,000 mAh', null],
        ['Power Banks', 'Anker', 'Anker Nano Power Bank 5000mAh with Built-In Lightning Connector', 8249, 'powerbank', '5,000 mAh', null],
        ['Power Banks', 'Anker', 'Anker Nano 10000mAh 45W Power Bank with Built-In Retractable USB-C Cable', 13499, 'powerbank', '10,000 mAh', null],
        ['Power Banks', 'Anker', 'Anker MagGo 10000mAh 15W Qi2 Magnetic Wireless Power Bank', 18599, 'powerbank', 'Qi2 15W', null],
        ['Power Banks', 'Anker', 'Anker Zolo 20000mAh 22.5W Power Bank with Built-In USB-C Cable', 11499, 'powerbank', '20,000 mAh', null],
        ['Power Banks', 'Anker', 'Anker A1383 87W 20000mAh Super Fast Charging Power Bank', 14499, 'powerbank', '20,000 mAh', null],
        ['Power Banks', 'Anker', 'Anker 535 Series 5 PowerCore 30W 20000mAh Power Bank (A1366)', 9499, 'powerbank', '20,000 mAh', null],
        ['Power Banks', 'Anker', 'Anker PowerCore III Elite 26000mAh 87W Portable Power Bank', 14799, 'powerbank', '26,000 mAh', null],
        ['Power Banks', 'Anker', 'Anker Prime Power Bank 9600mAh 65W (A1339111)', 21999, 'powerbank', '65W', null],

        // ---- Chargers & adapters ----
        ['Chargers & Adapters', 'Anker', 'Anker Zolo 20W 2 Pin Mini Charger (A2699J11)', 2849, 'charger', '20W', 'usbc'],
        ['Chargers & Adapters', 'Anker', 'Anker Zolo 30W 2 Pin Mini Charger (A2698J11)', 3349, 'charger', '30W', 'usbc'],
        ['Chargers & Adapters', 'Anker', 'Anker Nano 511 30W USB-C GaN Charger (A2147J11)', 4499, 'charger', '30W', 'usbc'],
        ['Chargers & Adapters', 'Anker', 'Anker Nano 45W Type-C Charger (A2692J11)', 5299, 'charger', '45W', 'usbc'],
        ['Chargers & Adapters', 'Anker', 'Anker A2667 PowerPort III 65W Super Charger', 7299, 'charger', '65W', 'usbc'],
        ['Chargers & Adapters', 'Anker', 'Anker A121A 70W Nano Charger', 7949, 'charger', '70W', 'usbc'],
        ['Chargers & Adapters', 'Anker', 'Anker Prime B2697JZ1 140W 4 Ports GaN Charger', 16999, 'charger', '140W', 'usbc'],
        ['Chargers & Adapters', 'Samsung', 'Samsung EP-TA800 25W 3 Pin Power Adapter', 2199, 'charger', '25W', 'usbc'],
        ['Chargers & Adapters', 'Samsung', 'Samsung EP-T2510 25W 2 Pin Low Standby Power Adapter', 2299, 'charger', '25W', 'usbc'],
        ['Chargers & Adapters', 'Samsung', 'Samsung 25W 2 Pin Travel Adapter', 2599, 'charger', '25W', 'usbc'],
        ['Chargers & Adapters', 'Samsung', 'Samsung 25W 3 Pin Travel Adapter', 2699, 'charger', '25W', 'usbc'],
        ['Chargers & Adapters', 'Samsung', 'Samsung 45W 2 Pin Travel Adapter', 3699, 'charger', '45W', 'usbc'],
        ['Chargers & Adapters', 'Samsung', 'Samsung EP-T4511 45W 3 Pin Power Adapter', 3899, 'charger', '45W', 'usbc'],
        ['Chargers & Adapters', 'Samsung', 'Samsung 25W 2 Pin PD Adapter with USB-C to USB-C Cable', 3999, 'charger', '25W', 'usbc'],
        ['Chargers & Adapters', 'Samsung', 'Samsung 50W 3 Pin PD Duo Power Adapter', 4799, 'charger', '50W', 'usbc'],
        ['Chargers & Adapters', 'Samsung', 'Samsung 65W 3 Pin Power Adapter Trio', 5849, 'charger', '65W', 'usbc'],

        // ---- Cables ----
        ['Cables', 'Urpin', 'Urpin UC02 20W Charging Cable', 599, 'cable', '20W', null],
        ['Cables', 'Urpin', 'Urpin UC01 Lightning 20W Fast Charging Cable', 649, 'cable', '20W', 'lightning'],
        ['Cables', 'Login', 'Login L-900 Charging Cable', 649, 'cable', null, null],
        ['Cables', 'Ronin', 'Ronin R-340 Charging Cable', 799, 'cable', null, null],
        ['Cables', 'FAYWA', 'FAYWA Lightning Charging Cable Supreme 1', 849, 'cable', 'Lightning', 'lightning'],
        ['Cables', 'Joyroom', 'Joyroom 60W Type-C to Type-C Cable (S-CC060A9)', 1199, 'cable', '60W', 'usbc'],
        ['Cables', 'Romoss', 'Romoss CB1713 27W USB-C to Lightning Super Fast Charging Cable', 1399, 'cable', '27W', 'lightning'],
        ['Cables', 'Aukey', 'Aukey USB-C to USB-C Cable (CB-CD45)', 1649, 'cable', 'USB-C', 'usbc'],
        ['Cables', 'Apple', 'Apple USB-C to USB-C Cable', 2699, 'cable', 'USB-C', 'usbc'],
        ['Cables', 'Aukey', 'Aukey Gen2 Type-C to Type-C Charging Cable (CB-CD21)', 2949, 'cable', 'USB-C', 'usbc'],
        ['Cables', 'Aukey', 'Aukey Type-C to Lightning Cable (CB-CL1)', 3349, 'cable', 'Lightning', 'lightning'],
        ['Cables', 'Xiaomi', 'Mi USB-C to Lightning Cable', 3349, 'cable', 'Lightning', 'lightning'],
        ['Cables', 'Choetech', 'Choetech USB-C to Lightning Charging Cable (IP0039)', 4299, 'cable', 'Lightning', 'lightning'],

        // ---- Earbuds ----
        ['Earbuds & Headphones', 'Airox', 'Airox E5 Wireless Earbuds', 1949, 'earbuds', 'Wireless', null],
        ['Earbuds & Headphones', 'Infinix', 'Infinix Buds Xeo4', 2949, 'earbuds', 'Wireless', null],
        ['Earbuds & Headphones', 'Kisonli', 'Kisonli F2 TWS Wireless Earbuds', 3149, 'earbuds', 'TWS', null],
        ['Earbuds & Headphones', 'Audiora', 'Audiora Bliss 715 Wireless Earbuds', 3449, 'earbuds', 'Wireless', null],
        ['Earbuds & Headphones', 'LDNIO', 'LDNIO T13 Wireless TWS Earbuds', 3599, 'earbuds', 'TWS', null],
        ['Earbuds & Headphones', 'WiWU', 'WiWU W02 ANC & ENC Plus True Wireless Earphones', 3999, 'earbuds', 'ANC', null],
        ['Earbuds & Headphones', 'QCY', 'QCY T13 Pro ANC Wireless Earbuds', 4199, 'earbuds', 'ANC', null],
        ['Earbuds & Headphones', 'QCY', 'QCY T13 ANC Wireless Earphones', 4499, 'earbuds', 'ANC', null],
        ['Earbuds & Headphones', 'Audionic', 'Audionic Airbud 550', 4999, 'earbuds', 'Wireless', null],
        ['Earbuds & Headphones', 'Soundpeats', 'Soundpeats Q3 Pro ANC Wireless Earbuds', 5699, 'earbuds', 'ANC', null],
        ['Earbuds & Headphones', 'Anker', 'Anker Soundcore R60i NC Wireless Earbuds', 8649, 'earbuds', 'NC', null],

        // ---- Wireless chargers ----
        ['Wireless Chargers', 'Mangotek', 'Mangotek 2-in-1 Wireless Magnetic Charger', 1849, 'wireless', 'Magnetic', null],
        ['Wireless Chargers', 'Hoco', 'HOCO CW6 Pro 15W Wireless Fast Charger', 2349, 'wireless', '15W', null],
        ['Wireless Chargers', 'Samsung', 'Samsung Wireless Charging Pad (Qi Certified)', 3899, 'wireless', 'Qi', null],
        ['Wireless Chargers', 'WiWU', 'WIWU WI-W034 Aramid Series 15W Wireless Charger', 3999, 'wireless', '15W', null],
        ['Wireless Chargers', 'Hoco', 'Hoco CW65 North 3-in-1 Desktop Wireless Fast Charger', 4199, 'wireless', '3-in-1', null],
        ['Wireless Chargers', 'Joyroom', 'Joyroom JR-WQN01 3-in-1 Wireless Charger', 6299, 'wireless', '3-in-1', null],
        ['Wireless Chargers', 'LITO', 'LITO LT-W02 Qi2 15W 4-in-1 Magnetic Wireless Charger', 7499, 'wireless', 'Qi2 15W', null],
        ['Wireless Chargers', 'Choetech', 'Choetech 3-in-1 Magnetic Wireless Charger 15W (T611F)', 8199, 'wireless', '3-in-1', null],

        // ---- Car accessories ----
        ['Car Accessories', 'Mars', 'Mars M-48 18W Car Charger', 949, 'car', '18W', null],
        ['Car Accessories', 'Airox', 'Airox 38W PD Plus Car Charger Adapter (CA-03)', 1049, 'car', '38W', null],
        ['Car Accessories', 'Westpoint', 'Westpoint PD 20W Car Charger (WP-90)', 1799, 'car', '20W', null],
        ['Car Accessories', 'Joyroom', 'Joyroom JR-CCN05 30W A+C Mini Metal Car Charger', 2349, 'car', '30W', null],
        ['Car Accessories', 'LDNIO', 'LDNIO 15W Adjustable Car Wireless Charger (MA02)', 2999, 'car', '15W', null],
        ['Car Accessories', 'Samsung', 'Samsung Dual Port Car Charger 15W', 3190, 'car', '15W', null],
        ['Car Accessories', 'Baseus', 'Baseus Golden Contactor Pro Dual Car Charger 40W', 3999, 'car', '40W', null],
        ['Car Accessories', 'Choetech', 'Choetech 130W Dual USB-C + USB-A Fast Car Charger (TC0011)', 5949, 'car', '130W', null],
        ['Car Accessories', 'Baseus', 'Baseus Qualcomm 5 Technology Multi Port Fast Car Charger 160W', 8799, 'car', '160W', null],
    ];

    private const CATEGORY_ICONS = [
        'Cases & Covers' => 'shield', 'Screen Protectors' => 'layers', 'Chargers & Adapters' => 'zap', 'Cables' => 'cable',
        'Power Banks' => 'battery', 'Wireless Chargers' => 'wifi', 'Earbuds & Headphones' => 'headphones', 'Car Accessories' => 'car',
        'Stands & Grips' => 'stand', 'Lens Protectors' => 'camera', 'Smartwatch Accessories' => 'watch', 'Bundles & Packs' => 'package',
    ];

    public function run(): void
    {
        $cats = [];
        foreach (array_keys(self::CATEGORY_ICONS) as $i => $name) {
            $cats[$name] = Category::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'icon' => self::CATEGORY_ICONS[$name], 'sort_order' => $i]);
        }

        // Real phone models (USB-C except iPhone 14 which uses Lightning).
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
        $made = [];

        foreach (self::PRODUCTS as $n => [$catName, $brandName, $name, $price, $type, $label, $port]) {
            $brand = Brand::firstOrCreate(['slug' => Str::slug($brandName)], ['name' => $brandName, 'sort_order' => 0]);
            $slug = Str::slug($name);

            $product = Product::firstOrCreate(['slug' => $slug], [
                'name' => $name, 'brand_id' => $brand->id, 'category_id' => $cats[$catName]->id, 'status' => 'active',
                'manufacturer' => $brandName,
                'short_description' => $name.'. Price listed in PKR; confirm specifications with the manufacturer before purchase.',
                'description' => $name."\n\nBrand: {$brandName}.\nThis listing was created from public Pakistani market pricing. Edit this description with verified specifications.",
                'compatibility_note' => match ($port) {
                    'usbc' => 'Works with devices that have a USB-C port.',
                    'lightning' => 'Works with devices that have a Lightning port.',
                    default => null,
                },
                'wholesale_enabled' => true, 'is_featured' => $n % 7 === 0, 'sales_count' => 0,
                'published_at' => now()->subDays(random_int(0, 20)),
            ]);

            $variant = $product->variants()->firstOrCreate(['sku' => Str::upper(Str::limit(Str::slug($brandName.' '.$name), 60, ''))], [
                'price' => $price, 'stock_quantity' => self::STOCK, 'low_stock_threshold' => 10,
                'wholesale_moq' => $price < 2000 ? 10 : 5, 'is_default' => true,
            ]);
            foreach ([[10, 49, .95], [50, 99, .90], [100, null, .85]] as [$min, $max, $f]) {
                $variant->tiers()->firstOrCreate(['min_quantity' => $min], ['max_quantity' => $max, 'price' => round($price * $f)]);
            }

            if ($product->images()->count() === 0) {
                $path = "products/{$slug}.svg";
                $disk->put($path, ProductIllustration::svg($type, (string) $label, $brandName, abs(crc32($name)) % 360));
                $product->images()->create(['path' => $path, 'alt' => $name]);
            }

            if ($port) {
                $product->deviceModels()->syncWithoutDetaching($byPort[$port]->all());
            }
            $made[$name] = $variant;
        }

        // Example bundle built from two real listings (price = 10% below buying separately; edit in admin).
        $parts = [$made['Samsung EP-TA800 25W 3 Pin Power Adapter'] ?? null, $made['Joyroom 60W Type-C to Type-C Cable (S-CC060A9)'] ?? null];
        if (! in_array(null, $parts, true)) {
            $bundle = Product::firstOrCreate(['slug' => 'samsung-25w-charger-joyroom-usb-c-cable-bundle'], [
                'name' => 'Samsung 25W Charger + Joyroom USB-C Cable Bundle', 'type' => 'bundle', 'status' => 'active', 'published_at' => now(),
                'category_id' => $cats['Bundles & Packs']->id, 'short_description' => 'Samsung EP-TA800 25W adapter with a Joyroom 60W USB-C cable.',
            ]);
            if ($bundle->variants()->count() === 0) {
                $bv = $bundle->variants()->create(['sku' => 'BUNDLE-SAMSUNG-25W-JOYROOM-USBC', 'price' => round(collect($parts)->sum('price') * 0.9), 'is_default' => true]);
                foreach ($parts as $p) {
                    BundleItem::create(['bundle_product_id' => $bundle->id, 'product_variant_id' => $p->id, 'quantity' => 1]);
                }
                $disk->put('products/bundle-samsung-joyroom.svg', ProductIllustration::svg('charger', 'Bundle', 'Samsung + Joyroom'));
                $bundle->images()->create(['path' => 'products/bundle-samsung-joyroom.svg', 'alt' => $bundle->name]);
            }
        }
    }
}
