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
            'categories' => Category::where('is_active', true)->whereNull('parent_id')->orderBy('sort_order')->get(),
            'deviceBrands' => DeviceBrand::where('is_active', true)->with(['deviceModels' => fn ($q) => $q->where('is_active', true)->limit(4)])->orderBy('sort_order')->get(),
            'bestSellers' => $cards()->orderByDesc('sales_count')->limit(8)->get(),
            'newArrivals' => $cards()->orderByDesc('published_at')->limit(8)->get(),
            'featured' => $cards()->where('is_featured', true)->limit(4)->get(),
            'deals' => $cards()->whereHas('variants', fn ($v) => $v->where('is_active', true)->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'price'))->limit(4)->get(),
            'bundles' => $cards()->where('type', 'bundle')->limit(4)->get(),
            // Only genuine, moderator-approved reviews are ever shown.
            'reviews' => Review::where('status', 'approved')->whereNotNull('body')->with('user:id,name', 'product:id,name,slug')->latest()->limit(3)->get(),
            'faqs' => Faq::where('is_active', true)->orderBy('sort_order')->get(),
            'trust' => array_filter([
                'Delivery' => StoreSetting::get('trust_delivery', 'Delivery across Pakistan with tracking on every shipment.'),
                'Payments' => 'Cash on delivery or bank transfer. We never store card details.',
                'Warranty' => StoreSetting::get('trust_warranty'),
                'Returns' => 'Return requests are handled per our returns policy.',
            ]),
        ]);
    }
}
