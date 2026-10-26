<?php

namespace App\Console\Commands;

use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Developer tool: collects real Pakistani listings from PriceOye into database/seed-assets/ so that
 * RealCatalogSeeder can run offline: name, brand, PKR price, specifications, colour options with their own
 * photo galleries and availability, and the public reviews shown for the product on the source site.
 *
 * LEGAL: product images, listing data and reviews belong to their owners (PriceOye, the brands, the reviewers).
 * Get permission before using them on a live store. Every record keeps its source URL for attribution/takedown.
 */
class ScrapeCatalog extends Command
{
    protected $signature = 'catalog:scrape {--limit=0 : Max products per listing page (0 = all)} {--no-images}';

    protected $description = 'Scrape real PriceOye listings, colour galleries, specs and reviews into database/seed-assets';

    private const IMAGE_BASE = 'https://images.priceoye.pk/';

    private const MAX_IMAGES_PER_COLOR = 3;

    private const LISTINGS = [
        ['https://priceoye.pk/power-banks', 'Power Banks'],
        ['https://priceoye.pk/power-banks/anker', 'Power Banks'],
        ['https://priceoye.pk/mobile-chargers', 'Chargers & Adapters'],
        ['https://priceoye.pk/mobile-chargers/anker', 'Chargers & Adapters'],
        ['https://priceoye.pk/mobile-chargers/samsung', 'Chargers & Adapters'],
        ['https://priceoye.pk/mobile-cables', 'Cables'],
        ['https://priceoye.pk/wireless-earbuds', 'Earbuds & Headphones'],
        ['https://priceoye.pk/wireless-chargers', 'Wireless Chargers'],
        ['https://priceoye.pk/mobile-car-chargers', 'Car Accessories'],
        ['https://priceoye.pk/mobile-car-chargers/samsung', 'Car Accessories'],
    ];

    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    public function handle(): int
    {
        $dir = database_path('seed-assets');
        File::ensureDirectoryExists("$dir/images");
        $items = [];

        foreach (self::LISTINGS as [$url, $category]) {
            $found = $this->parseListing(Http::withUserAgent(self::UA)->timeout(40)->get($url)->body(), $category);
            $this->line(count($found)." cards from $url");
            foreach (($this->option('limit') ? array_slice($found, 0, (int) $this->option('limit')) : $found) as $it) {
                $items[$it['page']] ??= $it;
            }
            usleep(300000);
        }

        $this->info(count($items).' unique products. Fetching details...');
        $out = [];
        foreach ($items as $it) {
            $detail = $this->detail($it);
            if ($detail === null) {
                $this->warn("skip (no detail): {$it['name']}");

                continue;
            }
            $out[] = $detail;
            usleep(250000);
        }

        File::put("$dir/catalog.json", json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->info(count($out).' products written; '.count(File::files("$dir/images")).' image files.');

        return self::SUCCESS;
    }

    /** @return array<int, array{name:string,page:string,price:int,image:?string,category:string}> */
    private function parseListing(string $html, string $category): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        $xp = new DOMXPath($dom);
        $rows = [];

        foreach ($xp->query('//div[contains(@class,"productBox")]') as $card) {
            $a = $xp->query('.//a[@href]', $card)->item(0);
            $name = trim($a?->getAttribute('data-vars-value') ?? '');
            $page = $a?->getAttribute('href');
            $priceTxt = $xp->query('.//div[contains(@class,"price-box")]', $card)->item(0)?->textContent ?? '';
            $price = (int) preg_replace('/\D/', '', preg_replace('/^\s*Rs/i', '', trim($priceTxt)));
            if (! $name || ! $page || $price <= 0) {
                continue;
            }
            $img = $xp->query('.//img', $card)->item(0)?->getAttribute('src');
            $img = $img ? preg_replace('/-\d+x\d+\.webp$/', '.webp', $img) : null;
            $rows[] = ['name' => html_entity_decode($name), 'page' => $page, 'price' => $price, 'image' => $img, 'category' => $category];
        }

        return $rows;
    }

    private function detail(array $it): ?array
    {
        $r = Http::withUserAgent(self::UA)->timeout(40)->get($it['page']);
        $d = $r->ok() ? $this->state($r->body()) : null;
        if (! $d) {
            return null;
        }

        $slug = Str::slug($it['name']);
        $data = $d['dataSet'] ?? [];

        // ---- specifications: {"Battery":[{"Capacity":"10000 mAh",...}], ...} -> ["Battery · Capacity" => "10000 mAh"]
        $specs = [];
        $raw = json_decode($data['specification'] ?? '', true);
        foreach ((array) $raw as $group => $rows) {
            foreach ((array) $rows as $row) {
                foreach ((array) $row as $k => $v) {
                    if (is_scalar($v) && trim((string) $v) !== '' && ! in_array(strtolower(trim((string) $v)), ['no', 'n/a', 'null'], true)) {
                        $specs["$group · $k"] = trim((string) $v);
                    }
                }
            }
        }

        // ---- colours: availability + price from dataPrices, galleries from product_color_images
        $colors = [];
        foreach ((array) ($d['product_config']['dataPrices'] ?? []) as $colorKey => $groups) {
            $entry = $groups[0][0] ?? null;
            if (! $entry) {
                continue;
            }
            $files = (array) ($d['product_color_images'][$colorKey]['large'] ?? []);
            $saved = [];
            foreach (array_slice($files, 0, self::MAX_IMAGES_PER_COLOR) as $n => $file) {
                $saved[] = $this->download(self::IMAGE_BASE.$file, "$slug-".Str::slug($colorKey)."-$n.webp");
            }
            $colors[] = [
                'name' => $colorKey === '0' || $colorKey === '' ? null : Str::title(str_replace(['-', '_'], ' ', $colorKey)),
                'price' => (int) preg_replace('/\D/', '', (string) ($entry['product_price'] ?? $it['price'])) ?: $it['price'],
                'available' => ($entry['product_availability'] ?? '') !== 'Out Of Stock' && (int) ($entry['stock_qty'] ?? 1) > 0,
                'images' => array_values(array_filter($saved)),
            ];
        }
        if ($colors === []) {
            $colors[] = ['name' => null, 'price' => $it['price'], 'available' => true, 'images' => []];
        }

        // Fallback main image from the listing when no colour gallery could be downloaded.
        $fallback = null;
        if (! array_filter(array_column($colors, 'images')) && $it['image']) {
            foreach (['', '-500x500', '-270x270'] as $suffix) {
                $fallback = $this->download(preg_replace('/\.webp$/', $suffix.'.webp', $it['image']), "$slug.webp");
                if ($fallback) {
                    break;
                }
            }
        }

        // ---- reviews shown on the source product page (public)
        $reviews = [];
        foreach (array_slice((array) ($d['ratings_data']['data'] ?? []), 0, 5) as $rv) {
            $text = trim((string) ($rv['description'] ?? ''));
            if ($text === '' || empty($rv['rating'])) {
                continue;
            }
            $reviews[] = ['name' => trim((string) ($rv['fullname'] ?? '')), 'rating' => (int) $rv['rating'], 'text' => $text, 'date' => substr((string) ($rv['date_create'] ?? ''), 0, 10)];
        }

        return [
            'name' => $it['name'], 'category' => $it['category'], 'page' => $it['page'], 'price' => $it['price'],
            'brand' => html_entity_decode((string) ($d['brand'] ?? $data['brand_name'] ?? '')) ?: null,
            'specs' => $specs, 'colors' => $colors, 'fallback_image' => $fallback,
            'source_rating' => ['average' => (float) ($d['average_rating'] ?? 0), 'count' => (int) ($d['total_ratings'] ?? 0)],
            'reviews' => $reviews,
        ];
    }

    /** Download only genuine WebP photos (the site serves an SVG "no image" placeholder for some files). */
    private function download(string $url, string $file): ?string
    {
        if ($this->option('no-images')) {
            return null;
        }
        $path = database_path("seed-assets/images/$file");
        if (File::exists($path)) {
            return $file;
        }
        $r = Http::withUserAgent(self::UA)->timeout(40)->get($url);
        $b = $r->body();
        if ($r->ok() && strlen($b) > 1000 && str_starts_with($b, 'RIFF') && substr($b, 8, 4) === 'WEBP') {
            File::put($path, $b);

            return $file;
        }

        return null;
    }

    /** Parses `window.product_data = {...};` embedded in the product page. */
    private function state(string $html): ?array
    {
        $p = strpos($html, 'window.product_data = ');
        if ($p === false) {
            return null;
        }
        $p += 22;
        $depth = 0;
        $in = false;
        $esc = false;
        $end = null;
        $n = strlen($html);
        for ($i = $p; $i < $n; $i++) {
            $ch = $html[$i];
            if ($in) {
                if ($esc) {
                    $esc = false;
                } elseif ($ch === chr(92)) {
                    $esc = true;
                } elseif ($ch === chr(34)) {
                    $in = false;
                }

                continue;
            }
            if ($ch === chr(34)) {
                $in = true;
            } elseif ($ch === '{') {
                $depth++;
            } elseif ($ch === '}' && --$depth === 0) {
                $end = $i;
                break;
            }
        }

        return $end ? json_decode(substr($html, $p, $end - $p + 1), true, 512, JSON_INVALID_UTF8_SUBSTITUTE) : null;
    }
}
