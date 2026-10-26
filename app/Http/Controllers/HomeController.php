<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\DeviceBrand;
use App\Models\Faq;
use App\Models\Product;

class HomeController extends Controller
{
    public function index()
    {
        $cards = fn () => Product::query()->active()
            ->with(['defaultVariant', 'images', 'brand'])
            ->withAvg('reviews', 'rating')->withCount('reviews');

        return view('home', [
            'hero' => Banner::where('placement', 'hero')->where('is_active', true)->orderBy('sort_order')->first(),
            'categories' => Category::where('is_active', true)->whereNull('parent_id')->orderBy('sort_order')->get(),
            'deviceBrands' => DeviceBrand::where('is_active', true)->with(['deviceModels' => fn ($q) => $q->where('is_active', true)->limit(4)])->orderBy('sort_order')->get(),
            'bestSellers' => $cards()->orderByDesc('sales_count')->limit(8)->get(),
            'newArrivals' => $cards()->orderByDesc('published_at')->limit(8)->get(),
            'featured' => $cards()->where('is_featured', true)->limit(4)->get(),
            'deals' => $cards()->whereHas('variants', fn ($v) => $v->where('is_active', true)->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'price'))->limit(4)->get(),
            'faqs' => Faq::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }
}
