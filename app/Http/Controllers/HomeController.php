<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\DeviceBrand;
use App\Models\Faq;
use App\Models\Product;
use App\Models\Review;
use App\Models\StoreSetting;

class HomeController extends Controller
{
    public function index()
    {
        $cards = fn () => Product::query()->active()
            ->with(['defaultVariant', 'images', 'brand'])
            ->withAvg('reviews', 'rating')->withCount('reviews');

        return view('home', [
            'hero' => Banner::where('placement', 'hero')->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))->orderBy('sort_order')->first(),
            'promo' => Banner::where('placement', 'promo')->where('is_active', true)->orderBy('sort_order')->limit(2)->get(),
            'categories' => Category::visible()->withCount(['products as active_count' => fn ($q) => $q->where('status', 'active')])->whereNull('parent_id')->orderBy('sort_order')->get(),
            'brands' => \App\Models\Brand::where('is_active', true)->whereHas('products', fn ($q) => $q->where('status', 'active'))->withCount('products')->orderByDesc('products_count')->limit(14)->get(),
            'stats' => ['products' => Product::active()->count(), 'brands' => \App\Models\Brand::whereHas('products', fn ($q) => $q->where('status', 'active'))->count()],
            'deviceBrands' => DeviceBrand::where('is_active', true)->with(['deviceModels' => fn ($q) => $q->where('is_active', true)->limit(4)])->orderBy('sort_order')->get(),
            'bestSellers' => $cards()->where(fn ($q) => $q->where('is_best_seller', true)->orWhere('sales_count', '>', 0))->orderByDesc('sales_count')->limit(8)->get(),
            'newArrivals' => $cards()->orderByDesc('published_at')->limit(8)->get(),
            // Hero: one product from each of four different categories (with a real image).
            'heroProducts' => $cards()->whereHas('images')->orderBy('id')->limit(400)->get()->unique('category_id')->take(4)->values(),
            'featured' => $cards()->where('is_featured', true)->limit(4)->get(),
            'deals' => $cards()->whereHas('variants', fn ($v) => $v->where('is_active', true)->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'price'))->limit(4)->get(),
            'bundles' => $cards()->where('type', 'bundle')->limit(4)->get(),
            // Only genuine, moderator-approved reviews are ever shown.
            'reviews' => Review::where('status', 'approved')->whereNotNull('body')->with('user:id,name', 'product:id,name,slug')->latest()->limit(3)->get(),
            'faqs' => Faq::where('is_active', true)->orderBy('sort_order')->get(),
            'trust' => array_filter([
                'Payments' => 'Cash on delivery or bank transfer. We never store card details.',
                'Delivery' => StoreSetting::get('trust_delivery'),
                'Warranty' => StoreSetting::get('trust_warranty'),
                'Returns' => 'Return requests are handled per our returns policy.',
            ]),
        ]);
    }
}
