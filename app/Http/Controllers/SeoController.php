<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class SeoController extends Controller
{
    public function sitemap()
    {
        $xml = Cache::remember('sitemap.xml', 3600, function () {
            $urls = collect([[route('home'), null], [route('shop'), null], [route('deals'), null], [route('new-arrivals'), null], [route('best-sellers'), null], [route('bundles'), null], [route('faqs'), null], [route('contact'), null]])
                ->merge(Category::where('is_active', true)->get()->map(fn ($c) => [route('category', $c), $c->updated_at]))
                ->merge(Brand::where('is_active', true)->get()->map(fn ($b) => [route('brand', $b), $b->updated_at]))
                ->merge(Page::where('is_published', true)->get()->map(fn ($p) => [route('page', $p), $p->updated_at]))
                ->merge(Product::active()->get(['id', 'slug', 'updated_at'])->map(fn ($p) => [route('product', $p), $p->updated_at]));

            return view('seo.sitemap', ['urls' => $urls])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots()
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin', 'Disallow: /cart', 'Disallow: /checkout', 'Disallow: /account', 'Disallow: /wholesale',
            'Disallow: /order', 'Disallow: /track-order', 'Disallow: /search',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
    }
}
