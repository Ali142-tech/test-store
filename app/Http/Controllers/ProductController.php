<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Pricing\PriceResolver;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show(Request $request, Product $product, PriceResolver $prices)
    {
        abort_if($product->status === 'archived', 410); // discontinued: tell crawlers it is gone
        abort_unless($product->status === 'active', 404);

        $product->load(['brand', 'category', 'images', 'variants' => fn ($q) => $q->where('is_active', true)->with('tiers'), 'deviceModels.deviceBrand'])
            ->loadAvg('reviews', 'rating')->loadCount('reviews');

        $user = $request->user();

        // Gallery per colour: a variant's own photos, else the shared (variant-less) photos, else everything.
        $byVariant = $product->images->groupBy('product_variant_id');
        $shared = $byVariant->get('', collect());
        $gallery = fn ($variantId) => ($byVariant->get($variantId) ?? ($shared->isNotEmpty() ? $shared : $product->images))
            ->map(fn ($i) => ['url' => $i->url(), 'alt' => $i->alt ?: $product->name])->values();

        $variants = $product->variants->map(fn ($v) => [
            'id' => $v->id,
            'name' => $v->name,
            'color' => $v->color,
            'swatch' => \App\Support\ColorMap::css($v->color),
            'images' => $gallery($v->id),
            'sku' => $v->sku,
            'price' => $prices->resolve($v, 1, $user)['price'],
            'regular_price' => (string) $v->price,
            'on_sale' => $v->onSale(),
            'available' => $v->available(),
            // Tier table is empty unless the user is an approved wholesale buyer (enforced in PriceResolver).
            'tiers' => $prices->visibleTiers($v, $user)->map(fn ($t) => [
                'min' => $t->min_quantity, 'max' => $t->max_quantity, 'price' => (string) $t->price,
            ])->values(),
            'moq' => $prices->minimumQuantity($v, $user),
        ])->values();

        $related = Product::active()->where('category_id', $product->category_id)->whereKeyNot($product->id)
            ->forCards()->limit(4)->get();

        // Recently viewed (per-session ids only; no personal data stored).
        $seen = collect(session('recently_viewed', []))->reject(fn ($id) => $id === $product->id)->prepend($product->id)->take(9)->values();
        session(['recently_viewed' => $seen->all()]);
        $recent = Product::active()->whereIn('id', $seen->slice(1))->forCards()->get()->sortBy(fn ($p) => $seen->search($p->id))->values();

        return view('shop.product', [
            'recent' => $recent,
            'product' => $product,
            'variants' => $variants,
            'related' => $related,
            'reviews' => $product->reviews()->with('user:id,name')->latest()->limit(10)->get(),
        ]);
    }
}
