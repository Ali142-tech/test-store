<?php

namespace App\Console\Commands;

use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Developer tool: collects real Pakistani listings (name, brand, price, colours, image) from PriceOye into
 * database/seed-assets/ so RealCatalogSeeder can run offline.
 *
 * LEGAL: product images and listing data belong to their owners (PriceOye, the brands and their photographers).
 * Check licence/permission before using them on a live store. Every record keeps its source_url for takedowns.
 */
class ScrapeCatalog extends Command
{
    protected $signature = 'catalog:scrape {--limit=0 : Max products per listing page (0 = all)} {--no-images}';

    protected $description = 'Scrape real PriceOye listings + images into database/seed-assets';

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
            $html = Http::withUserAgent(self::UA)->timeout(40)->get($url)->body();
            $found = $this->parseListing($html, $category);
            $this->line(count($found)." cards from $url");
            foreach (($this->option('limit') ? array_slice($found, 0, (int) $this->option('limit')) : $found) as $it) {
                $items[$it['page']] ??= $it;   // de-duplicate by product page
            }
            usleep(300000);
        }

        $this->info(count($items).' unique in-stock products. Fetching details...');
        $out = [];
        foreach ($items as $it) {
            $detail = $this->detail($it['page']);
            if ($detail === null) {
                $this->warn("skip (no detail): {$it['name']}");

                continue;
            }
            $it = array_merge($it, $detail);
            $slug = Str::slug($it['name']);
            $it['image_file'] = null;

            if (! $this->option('no-images') && $it['image']) {
                $file = "$slug.webp";
                $path = "$dir/images/$file";
if (! File::exists($path)) {
                    // The site serves an SVG "no image" placeholder for some products: only keep genuine WebP photos,
                    // trying smaller renditions when the full-size file is a placeholder.
                    foreach (['', '-500x500', '-270x270'] as $suffix) {
                        $r = Http::withUserAgent(self::UA)->timeout(40)->get(preg_replace('/\.webp$/', $suffix.'.webp', $it['image']));
                        $b = $r->body();
                        if ($r->ok() && strlen($b) > 1000 && str_starts_with($b, 'RIFF') && substr($b, 8, 4) === 'WEBP') {
                            File::put($path, $b);
                            break;
                        }
                    }
                }
                $it['image_file'] = File::exists($path) ? $file : null;
            }
            $out[] = $it;
            usleep(250000);
        }

        File::put("$dir/catalog.json", json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->info(count($out).' products written to database/seed-assets/catalog.json ('.count(File::files("$dir/images")).' images).');

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
            $text = strtolower($card->textContent);
            if (! $name || ! $page || $price <= 0 || str_contains($text, 'out of stock')) {
                continue;
            }
            $img = $xp->query('.//img', $card)->item(0)?->getAttribute('src');
            // Thumbnails end in -100x100.webp; the same name without the suffix is the full-size image.
            $img = $img ? preg_replace('/-\d+x\d+\.webp$/', '.webp', $img) : null;
            $rows[] = ['name' => html_entity_decode($name), 'page' => $page, 'price' => $price, 'image' => $img, 'category' => $category];
        }

        return $rows;
    }

    /** @return array{brand:?string,colors:array<int,string>,source_name:string}|null */
    private function detail(string $url): ?array
    {
        $r = Http::withUserAgent(self::UA)->timeout(40)->get($url);
        if (! $r->ok()) {
            return null;
        }
        $html = $r->body();
        $brand = null;
        if (preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m)) {
            foreach ($m[1] as $j) {
                $d = json_decode($j, true);
                if (($d['@type'] ?? null) === 'Product') {
                    $brand = is_array($d['brand'] ?? null) ? ($d['brand']['name'] ?? null) : ($d['brand'] ?? null);
                }
            }
        }

        $colors = [];
        $flat = preg_replace('/\s+/', ' ', preg_replace('/<[^>]+>/', '|', preg_replace('#<(script|style)\b.*?</\1>#s', ' ', $html)));
        if (preg_match('/\|\s*Colors\s*\|(.*?)Add Gift/i', $flat, $m)) {
            foreach (explode('|', $m[1]) as $c) {
                $c = trim($c);
                if ($c !== '' && strlen($c) <= 24 && ! preg_match('/\d{3}|Rs/i', $c)) {
                    $colors[] = $c;
                }
            }
        }

        return ['brand' => $brand ? html_entity_decode($brand) : null, 'colors' => array_values(array_unique($colors)), 'source_name' => $url];
    }
}
