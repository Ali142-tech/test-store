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

        $variants = $product->variants->map(fn ($v) => [
            'id' => $v->id,
            'name' => $v->name,
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
            ->with(['defaultVariant', 'images', 'brand'])->withAvg('reviews', 'rating')->withCount('reviews')->limit(4)->get();

        return view('shop.product', [
            'product' => $product,
            'variants' => $variants,
            'related' => $related,
            'reviews' => $product->reviews()->with('user:id,name')->latest()->limit(10)->get(),
        ]);
    }
}
