<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    private const SORTS = ['relevance', 'newest', 'price_asc', 'price_desc', 'popular', 'rating'];

    public function index(Request $request)
    {
        return $this->listing($request, Product::query(), 'Shop all');
    }

    public function category(Request $request, Category $category)
    {
        abort_unless($category->is_active, 404);
        $ids = $category->children()->pluck('id')->push($category->id);

        return $this->listing($request, Product::query()->whereIn('category_id', $ids), $category->name, $category->description);
    }

    public function brand(Request $request, Brand $brand)
    {
        abort_unless($brand->is_active, 404);

        return $this->listing($request, Product::query()->where('brand_id', $brand->id), $brand->name, $brand->description);
    }

    public function device(Request $request, DeviceBrand $deviceBrand, DeviceModel $deviceModel)
    {
        abort_unless($deviceModel->device_brand_id === $deviceBrand->id, 404);

        return $this->listing(
            $request,
            Product::query()->whereHas('deviceModels', fn ($q) => $q->where('device_models.id', $deviceModel->id)),
            "Accessories for {$deviceModel->name}"
        );
    }

    public function deals(Request $request)
    {
        return $this->listing($request, Product::query()->whereHas('variants', fn ($v) => $this->onSale($v)), 'Deals & offers');
    }

    public function newArrivals(Request $request)
    {
        $request->merge(['sort' => $request->input('sort', 'newest')]);

        return $this->listing($request, Product::query()->where('published_at', '>=', now()->subDays(45)), 'New arrivals');
    }

    public function bundles(Request $request)
    {
        return $this->listing($request, Product::query()->where('type', 'bundle'), 'Bundles & combo deals', 'Save more when you buy accessories together.');
    }

    public function bestSellers(Request $request)
    {
        $request->merge(['sort' => $request->input('sort', 'popular')]);

        return $this->listing($request, Product::query()->where(fn ($q) => $q->where('is_best_seller', true)->orWhere('sales_count', '>', 0)), 'Best sellers');
    }

    private function onSale($variants): Builder
    {
        return $variants->where('is_active', true)
            ->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'price')
            ->where(fn ($q) => $q->whereNull('sale_starts_at')->orWhere('sale_starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>=', now()));
    }

    private function listing(Request $request, Builder $query, string $title, ?string $intro = null)
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'brand' => 'nullable|string|max:100',
            'device' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'in_stock' => 'nullable|boolean',
            'on_sale' => 'nullable|boolean',
            'sort' => 'nullable|in:'.implode(',', self::SORTS),
        ]);

        $query->active()
            ->forCards();

        if ($q = $data['q'] ?? null) {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)
                    ->orWhere('short_description', 'like', $like)
                    ->orWhere('manufacturer', 'like', $like)
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $like))
                    ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like))
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like))
                    ->orWhereHas('deviceModels', fn ($d) => $d->where('name', 'like', $like));
            });
        }
        if ($v = $data['category'] ?? null) {
            $query->whereHas('category', fn ($c) => $c->where('slug', $v));
        }
        if ($v = $data['brand'] ?? null) {
            $query->whereHas('brand', fn ($b) => $b->where('slug', $v));
        }
        if ($v = $data['device'] ?? null) {
            $query->whereHas('deviceModels', fn ($d) => $d->where('slug', $v));
        }
        if ($v = $data['color'] ?? null) {
            $query->whereHas('variants', fn ($x) => $x->where('color', $v));
        }
        if (isset($data['min_price'])) {
            $query->whereHas('variants', fn ($x) => $x->where('price', '>=', $data['min_price']));
        }
        if (isset($data['max_price'])) {
            $query->whereHas('variants', fn ($x) => $x->where('price', '<=', $data['max_price']));
        }
        if (! empty($data['in_stock'])) {
            $query->inStock();
        }
        if (! empty($data['on_sale'])) {
            $query->whereHas('variants', fn ($x) => $this->onSale($x));
        }

        // Available products always come before sold-out ones, whatever the sort.
        $query->orderByRaw('(select count(*) from product_variants pv where pv.product_id = products.id and pv.is_active = 1 and pv.deleted_at is null and pv.stock_quantity - pv.reserved_quantity > 0) > 0 desc');

        match ($data['sort'] ?? 'relevance') {
            'newest' => $query->orderByDesc('published_at'),
            'popular' => $query->orderByDesc('sales_count'),
            'rating' => $query->orderByDesc('reviews_avg_rating'),
            'price_asc' => $query->orderBy($this->minPrice(), 'asc'),
            'price_desc' => $query->orderBy($this->minPrice(), 'desc'),
            default => $query->orderByDesc('is_featured')->orderByDesc('sales_count'),
        };

        $page = $request->integer('page', 1);

        return view('shop.index', [
            'canonical' => $page > 1 ? url()->current().'?page='.$page : url()->current(),
            'title' => $title,
            'intro' => $intro,
            'products' => $query->paginate(12)->withQueryString(),
            'filters' => $data,
            'categories' => Category::visible()->whereNull('parent_id')->orderBy('sort_order')->get(),
            'brands' => Brand::where('is_active', true)->orderBy('name')->get(),
            'colors' => \App\Models\ProductVariant::whereNotNull('color')->distinct()->orderBy('color')->pluck('color'),
        ]);
    }

    private function minPrice(): \Illuminate\Database\Query\Expression
    {
        return \DB::raw('(select min(price) from product_variants pv where pv.product_id = products.id and pv.is_active = 1 and pv.deleted_at is null)');
    }
}
