<?php

namespace App\Console\Commands;

use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Developer tool: collects real Pakistani market listings (gaming consoles, controllers, keyboards, mice, headsets,
 * chairs, VR) into database/seed-assets/ so RealCatalogSeeder can run offline: name, brand, PKR market price,
 * specifications, colour options (with price/availability) and product photos.
 *
 * Source: Qeemat (qeemat.com.pk), a Pakistani price-comparison site. The price is the LOWEST current market offer, i.e. a
 * market reference, not your selling price: set your own prices in Admin > Products. Links to other shops are never stored.
 *
 * LEGAL: photos and listing data belong to their owners (Qeemat, the brands and photographers). Get permission before using
 * them on a live store. Each record keeps its source page URL (internal only) for attribution/takedown.
 */
class ScrapeCatalog extends Command
{
    protected $signature = 'catalog:scrape {--pages=0 : Cap pages per category (0 = default caps)} {--min-price=25000} {--no-images}';

    protected $description = 'Scrape real Pakistani gaming listings (prices, specs, colours, photos) into database/seed-assets';

    private const BASE = 'https://qeemat.com.pk';

    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    /** slug => [store category, max pages, filter] ; filter: all | gaming | gaming_or_premium */
    private const LISTINGS = [
        'game-consoles' => ['Game Consoles', 3, 'all'],
        'game-controllers' => ['Controllers & Gamepads', 10, 'all'],
        'keyboard' => ['Gaming Keyboards', 41, 'gaming'],
        'mouse' => ['Gaming Mice', 13, 'gaming'],
        'headphones' => ['Gaming Headsets & Headphones', 25, 'gaming_or_premium'],
        'monitors' => ['Gaming Monitors', 10, 'gaming_or_premium'],
        'vr-headsets-accessories' => ['VR Headsets', 1, 'all'],
    ];

    private const GAMING = '/razer|logitech g|logitech pro|g pro|g502|g915|g733|g435|steelseries|arctis|apex|rog|republic of gamers|corsair|hyperx|keychron|redragon|msi |akko|skyloong|attack shark|royal kludge|rk[0-9]|wooting|ducky|glorious|cooler master|legion|predator|aorus|fantech|havit|zowie|benq|turtle beach|jbl quantum|astro|roccat|alienware|nzxt|gamesir|8bitdo|pulse|playstation|xbox|nintendo|gaming|mechanical|rgb|lamzu|pulsar|endgame|ajazz|bloody|g-wolves|vgn|zaopin|madlions|wolf king|odyssey|tuf|nitro|curved|1[0-9]{2}hz|2[0-9]{2}hz|oled|ultrawide|xg[0-9]|g[0-9]{2}[a-z]|sennheiser|sony|bose|audeze|beyerdynamic|dolby/i';

    private const COLORS = ['Black', 'White', 'Blue', 'Red', 'Green', 'Grey', 'Gray', 'Silver', 'Pink', 'Purple', 'Orange', 'Yellow', 'Gold', 'Beige', 'Brown', 'Navy', 'Cream', 'Mint', 'Lavender', 'Violet', 'Titanium', 'Midnight', 'Starlight', 'Cyan', 'Teal', 'Rose'];

    public function handle(): int
    {
        $dir = database_path('seed-assets');
        File::ensureDirectoryExists("$dir/images");
        $min = (int) $this->option('min-price');
        $cards = [];

        foreach (self::LISTINGS as $slug => [$category, $maxPages, $filter]) {
            $cap = (int) $this->option('pages') ?: $maxPages;
            $kept = 0;
            for ($page = 1; $page <= $cap; $page++) {
                $r = $this->fetch(self::BASE."/cl/$slug", $page > 1 ? ['page' => $page] : []);
                $found = $r ? $this->parseListing($r->body()) : [];
                if ($found === []) {
                    break;
                }
                foreach ($found as $c) {
                    if ($c['price'] >= $min && $this->passes($filter, $c)) {
                        $cards[$c['page']] ??= $c + ['category' => $category];
                        $kept++;
                    }
                }
                usleep(250000);
            }
            $this->line("$category: $kept kept");
        }

        $this->info(count($cards).' products to fetch...');
        $details = [];
        foreach ($cards as $c) {
            $d = $this->detail($c);
            if ($d) {
                $details[] = $d;
            } else {
                $this->warn('skipped '.$c['page']);
            }
            usleep(200000);
        }

        $out = $this->groupColours($details);
        File::put("$dir/catalog.json", json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->info(count($out).' products ('.array_sum(array_map(fn ($p) => count($p['colors']), $out)).' variants); '.count(File::files("$dir/images")).' image files.');

        return self::SUCCESS;
    }

    /** GET with retries; the source resets connections now and then. Returns null when it keeps failing. */
    private function fetch(string $url, array $query = []): ?\Illuminate\Http\Client\Response
    {
        for ($try = 1; $try <= 4; $try++) {
            try {
                $r = Http::withUserAgent(self::UA)->timeout(60)->get($url, $query);
                if ($r->ok()) {
                    return $r;
                }
            } catch (\Throwable) {
            }
            sleep($try);
        }

        return null;
    }

    private function passes(string $filter, array $c): bool
    {
        $gaming = (bool) preg_match(self::GAMING, $c['name']);

        return match ($filter) {
            'gaming' => $gaming,
            'gaming_or_premium' => $gaming || $c['price'] >= 45000,
            default => true,
        };
    }

    /** @return array<int, array{name:string,page:string,price:int}> */
    private function parseListing(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        $xp = new DOMXPath($dom);
        $out = [];
        foreach ($xp->query('//a[contains(@href,"/dp/")][@aria-label]') as $a) {
            if (preg_match('/^(.*) - Best price Rs\.?\s*([\d,]+)/u', $a->getAttribute('aria-label'), $m)) {
                $img = $xp->query('.//img', $a)->item(0);
                $out[$a->getAttribute('href')] = ['name' => html_entity_decode(trim($m[1])), 'price' => (int) str_replace(',', '', $m[2]), 'page' => $a->getAttribute('href'),
                    'img' => $img ? ($img->getAttribute('src') ?: $img->getAttribute('data-src')) : null];
            }
        }

        return array_values($out);
    }

    private function detail(array $c): ?array
    {
        $r = $this->fetch($c['page']);
        if (! $r) {
            return null;
        }
        $html = $r->body();
        $product = null;
        $groupBrand = null;
        if (preg_match_all('#<script[^>]*application/ld\+json[^>]*>(.*?)</script>#s', $html, $m)) {
            foreach ($m[1] as $j) {
                $d = json_decode($j, true);
                // a block is either one object or a list (ProductGroup + Product for variant pages)
                foreach (is_array($d) && array_is_list($d) ? $d : [$d] as $node) {
                    $type = $node['@type'] ?? '';
                    if ($type === 'Product') {
                        $product = $node;
                    } elseif ($type === 'ProductGroup') {
                        $groupBrand = $node['brand']['name'] ?? null;
                    }
                }
            }
        }
        if (! $product) {
            return null;
        }

        $offers = $product['offers'] ?? [];
        $price = (int) round($offers['lowPrice'] ?? $offers['price'] ?? $c['price']);
        $inStock = collect($offers['offers'] ?? [])->contains(fn ($o) => str_contains((string) ($o['availability'] ?? ''), 'InStock'));
        $slug = Str::slug($c['name']);

        $images = [];
        foreach (array_slice((array) ($product['image'] ?? []), 0, 3) as $n => $url) {
            $url = is_array($url) ? ($url['url'] ?? '') : $url;
            if ($f = $this->download((string) $url, "$slug-$n.webp")) {
                $images[] = $f;
            }
        }

        // the structured-data photo is sometimes a dead link for variant pages: fall back to the listing thumbnail
        if ($images === [] && ! empty($c['img']) && ($f = $this->download((string) $c['img'], "$slug-card.webp"))) {
            $images[] = $f;
        }

        return [
            'name' => $c['name'], 'category' => $c['category'], 'page' => $c['page'], 'price' => $price,
            'brand' => (is_array($product['brand'] ?? null) ? ($product['brand']['name'] ?? null) : ($product['brand'] ?? null)) ?: $groupBrand,
            'specs' => $this->parseSpecs($html), 'available' => $inStock, 'images' => $images,
        ];
    }

    /** "General Information" => [Brand => X] becomes ["General Information · Brand" => "X"]. */
    private function parseSpecs(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        $xp = new DOMXPath($dom);
        $specs = [];
        foreach ($xp->query('//h2[normalize-space()="Specifications"]/following-sibling::div[1]//h3') as $h3) {
            $group = trim(preg_replace('/\s+/', ' ', $h3->textContent));
            $dl = $xp->query('following-sibling::div[1]//dl', $h3)->item(0);
            foreach ($dl ? $xp->query('.//div[dt and dd]', $dl) : [] as $row) {
                $k = trim($xp->query('dt', $row)->item(0)?->textContent ?? '');
                $v = trim(preg_replace('/\s+/', ' ', $xp->query('dd', $row)->item(0)?->textContent ?? ''));
                if ($k !== '' && $v !== '' && ! in_array(strtolower($v), ['n/a', '-', 'no', 'null'], true) && ! in_array($k, ['Category'], true)) {
                    $specs["$group · $k"] = $v;
                }
            }
        }

        return $specs;
    }

    private function download(string $url, string $file): ?string
    {
        if ($this->option('no-images') || ! str_starts_with($url, 'http')) {
            return null;
        }
        $path = database_path("seed-assets/images/$file");
        if (File::exists($path)) {
            return $file;
        }
        $r = $this->fetch($url);
        $b = $r?->body() ?? '';
        // keep only genuine WebP photos
        if ($r && strlen($b) > 1000 && str_starts_with($b, 'RIFF') && substr($b, 8, 4) === 'WEBP') {
            File::put($path, $b);

            return $file;
        }

        return null;
    }

    /**
     * The source lists each colour as its own product ("... Black" / "... White"). Group them into one product with a
     * variant per colour (own price, availability and photos).
     */
    private function groupColours(array $items): array
    {
        $pattern = '/\s+('.implode('|', self::COLORS).')(\s+(?:Edition|Version))?$/i';
        $groups = [];
        foreach ($items as $it) {
            $color = null;
            $base = $it['name'];
            if (preg_match($pattern, $it['name'], $m)) {
                $color = Str::title($m[1]);
                $base = trim(preg_replace($pattern, '', $it['name']));
            }
            $key = Str::lower($base).'|'.$it['category'];
            $groups[$key] ??= ['base' => $base, 'items' => []];
            $groups[$key]['items'][] = $it + ['color' => $color];
        }

        $out = [];
        foreach ($groups as $g) {
            $first = $g['items'][0];
            $multi = count($g['items']) > 1 && collect($g['items'])->every(fn ($i) => $i['color']);
            $colors = $multi
                ? array_map(fn ($i) => ['name' => $i['color'], 'price' => $i['price'], 'available' => $i['available'], 'images' => $i['images'], 'page' => $i['page']], $g['items'])
                : [['name' => $first['color'], 'price' => $first['price'], 'available' => $first['available'], 'images' => $first['images'], 'page' => $first['page']]];
            if (! $multi && count($g['items']) > 1) {
                // same base name but not all coloured: keep each as its own product
                foreach ($g['items'] as $i) {
                    $out[] = $this->product($i['name'], $i, [['name' => $i['color'], 'price' => $i['price'], 'available' => $i['available'], 'images' => $i['images'], 'page' => $i['page']]]);
                }

                continue;
            }
            $out[] = $this->product($multi ? $g['base'] : $first['name'], $first, $colors);
        }

        return $out;
    }

    private function product(string $name, array $first, array $colors): array
    {
        return ['name' => $name, 'category' => $first['category'], 'page' => $first['page'], 'price' => min(array_column($colors, 'price')),
            'brand' => $first['brand'], 'specs' => $first['specs'], 'colors' => $colors, 'fallback_image' => null];
    }
}
