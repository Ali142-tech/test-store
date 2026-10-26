@php
    use App\Support\Money;
    $first = $variants->first();
    $desc = $product->seo_description ?: $product->short_description;
@endphp
@extends('layouts.app', [
    'metaTitle' => $product->seo_title ?: $product->name,
    'metaDescription' => $desc,
    'ogType' => 'product',
    'ogImage' => $product->images->first()?->url(),
])

@push('head')
<script type="application/ld+json">
{!! json_encode(array_filter([
    '@context' => 'https://schema.org', '@type' => 'Product',
    'name' => $product->name, 'description' => $desc, 'sku' => $first['sku'] ?? null,
    'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
    'image' => $product->images->map->url()->all() ?: null,
    'offers' => $first ? ['@type' => 'Offer', 'priceCurrency' => 'PKR', 'price' => $first['price'], 'availability' => $first['available'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock', 'url' => url()->current()] : null,
    // Rating markup only when genuine approved reviews exist.
    'aggregateRating' => $realReviews->count > 0 ? ['@type' => 'AggregateRating', 'ratingValue' => round($realReviews->avg, 1), 'reviewCount' => $realReviews->count] : null,
]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
</script>
@endpush

@push('head')
<script>
function productPage(variants, symbol) {
    return {
        variants, selected: 0, qty: 1, img: 0,
        get gallery() { return this.v ? this.v.images : []; },
        get v() { return this.variants[this.selected]; },
        get minQty() { return this.v ? this.v.moq : 1; },
        get q() { const n = parseInt(this.qty, 10); return Number.isFinite(n) && n > 0 ? n : 1; },
        // Unit price for the chosen quantity: wholesale tiers (only ever sent to approved wholesale buyers) else the variant price.
        get unit() { const t = (this.v.tiers || []).filter(t => this.q >= t.min && (t.max === null || this.q <= t.max)).sort((a, b) => b.min - a.min)[0]; return t ? Number(t.price) : Number(this.v.price); },
        get total() { return this.unit * this.q; },
        clamp() { this.qty = Math.min(Math.max(this.q, this.minQty), Math.max(this.v.available, 1)); },
        init() { const first = this.variants.findIndex(x => x.available > 0); if (first > 0) this.selected = first; this.qty = this.minQty; },
        pick(i) { this.selected = i; this.img = 0; this.qty = Math.min(Math.max(this.qty, this.minQty), Math.max(this.v.available, 1)); },
        money(n) { return symbol + ' ' + Number(n).toLocaleString('en-US', { maximumFractionDigits: 0 }); },
    };
}
</script>
@endpush
@push('head')
<script type="application/ld+json">
{!! json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_values(array_filter([
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
    $product->category ? ['@type' => 'ListItem', 'position' => 2, 'name' => $product->category->name, 'item' => route('category', $product->category)] : null,
    ['@type' => 'ListItem', 'position' => $product->category ? 3 : 2, 'name' => $product->name, 'item' => url()->current()],
]))], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
</script>
@endpush

@section('content')
<div class="container-x py-10" x-data="productPage(@js($variants), @js(\App\Models\StoreSetting::get('currency_symbol', 'Rs')))">
    <nav class="text-xs text-ink-soft" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a>
        @if ($product->category) / <a href="{{ route('category', $product->category) }}">{{ $product->category->name }}</a> @endif
        / <span>{{ $product->name }}</span>
    </nav>

    <div class="mt-6 grid gap-10 lg:grid-cols-2">
        <div>
            <div class="relative aspect-square overflow-hidden rounded-[var(--radius-card)] bg-mist ring-1 ring-line">
                <template x-if="gallery.length"><img :src="gallery[img].url" :alt="gallery[img].alt" class="h-full w-full object-contain p-2"></template>
                <template x-if="!gallery.length"><div class="flex h-full items-center justify-center bg-gradient-to-br from-accent-soft to-mist text-7xl font-semibold text-accent/50" aria-hidden="true">{{ mb_substr($product->name, 0, 1) }}</div></template>
            </div>
            <div class="mt-3 flex gap-2 overflow-x-auto" x-show="gallery.length > 1">
                <template x-for="(g, k) in gallery" :key="g.url"><button type="button" @click="img = k" class="h-16 w-16 shrink-0 overflow-hidden rounded-xl border bg-mist" :class="img === k ? 'border-accent' : 'border-line'"><img :src="g.url" alt="" class="h-full w-full object-contain" loading="lazy"></button></template>
            </div>
        </div>

        <div>
            <p class="text-sm text-ink-soft">{{ $product->brand?->name }}</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight">{{ $product->name }}</h1>
            @if ($product->reviews_count > 0)
                <p class="mt-2 text-sm"><span class="text-amber-500">★</span> {{ number_format($product->reviews_avg_rating, 1) }} · {{ $product->reviews_count }} reviews</p>
            @endif

            <div class="mt-5 flex items-baseline gap-3">
                <span class="text-3xl font-semibold" x-text="money(unit)"></span>
                <span class="text-lg text-ink-soft line-through" x-show="unit < Number(v.regular_price)" x-text="money(v.regular_price)"></span>
                <span class="rounded-full bg-accent-soft px-2.5 py-1 text-xs font-semibold text-accent" x-show="unit < Number(v.regular_price)"
                      x-text="Math.round((1 - unit / v.regular_price) * 100) + '% off'"></span>
                <span class="text-sm text-ink-soft">each</span>
            </div>
            <p class="mt-1 text-sm" x-show="q > 1" x-cloak><span x-text="q + ' × ' + money(unit) + ' ='"></span> <strong class="text-lg" x-text="money(total)"></strong></p>
            <p class="mt-3 text-ink-soft">{{ $product->short_description }}</p>

            @if ($variants->count() > 1)
                <fieldset class="mt-6">
                    <legend class="text-sm font-semibold">{{ $variants->contains(fn ($v) => $v['color']) ? 'Colour' : 'Option' }}: <span class="font-normal text-ink-soft" x-text="v.name"></span></legend>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <template x-for="(vr, idx) in variants" :key="vr.id">
                            <button type="button" @click="pick(idx)" class="chip gap-2 disabled:opacity-40" :class="selected === idx ? '!border-accent bg-accent-soft' : ''" :disabled="vr.available === 0"><span x-show="vr.swatch" class="h-4 w-4 rounded-full ring-1 ring-black/15" :style="{ background: vr.swatch }"></span><span x-text="vr.name"></span><span x-show="vr.available === 0" class="text-xs text-ink-soft">(sold out)</span></button>
                        </template>
                    </div>
                </fieldset>
            @endif

            <p class="mt-5 text-sm">
                <span x-show="v.available > 10" class="text-ok">● In stock</span>
                <span x-show="v.available > 0 && v.available <= 10" class="text-warn" x-text="'Only ' + v.available + ' left'"></span>
                <span x-show="v.available === 0" class="text-danger">Out of stock</span>
            </p>

            <template x-if="v.tiers.length">
                <div class="mt-5 rounded-2xl border border-line p-4 text-sm">
                    <p class="font-semibold">Wholesale pricing</p>
                    <p class="text-ink-soft" x-show="v.moq > 1" x-text="'Minimum order: ' + v.moq + ' units'"></p>
                    <table class="mt-2 w-full"><tbody>
                        <template x-for="t in v.tiers" :key="t.min"><tr><td class="py-1" x-text="t.min + (t.max ? '–' + t.max : '+') + ' units'"></td><td class="text-right font-medium" x-text="money(t.price)"></td></tr></template>
                    </tbody></table>
                </div>
            </template>

            @error('cart') <p class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-danger" role="alert">{{ $message }}</p> @enderror
            <form method="post" action="{{ route('cart.add') }}" class="mt-6 flex flex-wrap items-center gap-3">
                @csrf
                <input type="hidden" name="variant_id" :value="v.id">
                <div class="flex items-center rounded-full border border-line">
                    <button type="button" class="h-11 w-11" @click="qty = Math.max(minQty, q - 1)" aria-label="Decrease">−</button>
                    <input type="number" name="quantity" x-model.number="qty" @change="clamp()" @blur="clamp()" :min="minQty" :max="v.available" class="w-14 border-0 bg-transparent text-center focus:outline-none" aria-label="Quantity">
                    <button type="button" class="h-11 w-11" @click="qty = Math.min(v.available, q + 1)" aria-label="Increase">+</button>
                </div>
                <button class="btn btn-primary flex-1 disabled:opacity-40" :disabled="v.available === 0">Add to cart</button>
                <button class="btn btn-accent disabled:opacity-40" name="buy_now" value="1" :disabled="v.available === 0">Buy now</button>
            </form>
            @auth
                <form method="post" action="{{ route('account.wishlist.toggle', $product) }}" class="mt-3">@csrf
                    <button class="text-sm text-ink-soft underline">{{ auth()->user()->wishlistItems()->where('product_id', $product->id)->exists() ? 'Remove from wishlist' : 'Add to wishlist' }}</button>
                </form>
            @endauth

            <dl class="mt-8 divide-y divide-line border-y border-line text-sm">
                @if ($product->warranty) <div class="flex justify-between py-3"><dt class="text-ink-soft">Warranty</dt><dd>{{ $product->warranty }}</dd></div> @endif
                @if ($product->manufacturer) <div class="flex justify-between py-3"><dt class="text-ink-soft">Manufacturer</dt><dd>{{ $product->manufacturer }}</dd></div> @endif
                @if ($product->is_authentic_verified && $product->authenticity_note) <div class="flex justify-between py-3"><dt class="text-ink-soft">Authenticity</dt><dd>{{ $product->authenticity_note }}</dd></div> @endif
                <div class="flex justify-between py-3"><dt class="text-ink-soft">SKU</dt><dd x-text="v.sku"></dd></div>
            </dl>
        </div>
    </div>

    <div class="mt-14 grid gap-10 lg:grid-cols-2">
        <section>
            <h2 class="text-xl font-semibold">Description</h2>
            <div class="mt-3 text-ink-soft whitespace-pre-line">{{ $product->description }}</div>
        </section>
        <section>
            @if ($product->deviceModels->isNotEmpty())
                <h2 class="text-xl font-semibold">Compatible devices</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($product->deviceModels as $m) <a class="chip" href="{{ route('device', [$m->deviceBrand, $m]) }}">{{ $m->name }}</a> @endforeach
                </div>
            @endif
            @if ($product->specifications)
                <h2 class="mt-8 text-xl font-semibold">Specifications</h2>
                <dl class="mt-3 divide-y divide-line text-sm">
                    @foreach ($product->specifications as $k => $val) <div class="flex justify-between py-2"><dt class="text-ink-soft">{{ $k }}</dt><dd>{{ $val }}</dd></div> @endforeach
                </dl>
            @endif
        </section>
    </div>

    <section class="mt-14">
        <h2 class="text-xl font-semibold">Customer reviews</h2>
        @forelse ($reviews as $r)
            <div class="mt-4 border-b border-line pb-4">
                <p class="text-sm"><span class="text-amber-500">{{ str_repeat('★', $r->rating) }}</span> <strong>{{ $r->title }}</strong></p>
                <p class="mt-1 text-sm text-ink-soft">{{ $r->body }}</p>
                <p class="mt-1 text-xs text-ink-soft">{{ $r->user->name }} @if ($r->is_verified_purchase) · Verified purchase @endif @if ($r->is_sample) <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">Sample review</span> @endif</p>
            </div>
        @empty
            <p class="mt-3 text-sm text-ink-soft">No reviews yet.</p>
        @endforelse

        <div class="mt-6 rounded-2xl bg-mist p-5 text-sm">
            @if ($canReview)
                <p class="font-semibold">{{ $myReview ? 'Update your review' : 'Write a review' }}</p>
                @if ($myReview && $myReview->status === 'pending')<p class="mt-1 text-ink-soft">Your review is awaiting approval.</p>@endif
                <form method="post" action="{{ route('account.reviews.store', $product) }}" class="mt-3 grid gap-3 sm:grid-cols-[8rem_1fr]">@csrf
                    <label class="sr-only" for="rv-rating">Rating</label>
                    <select id="rv-rating" name="rating" class="field">@for ($r = 5; $r >= 1; $r--)<option value="{{ $r }}" @selected(($myReview->rating ?? 5) === $r)>{{ $r }} ★</option>@endfor</select>
                    <input name="title" class="field" placeholder="Headline (optional)" value="{{ $myReview->title ?? '' }}" maxlength="120">
                    <textarea name="body" rows="3" class="field sm:col-span-2" placeholder="What did you think of it?" maxlength="2000">{{ $myReview->body ?? '' }}</textarea>
                    <button class="btn btn-primary w-fit">Submit review</button>
                </form>
            @else
                <p class="text-ink-soft">Only customers who have received this product can review it, so every review here comes from a real buyer.</p>
            @endif
        </div>
    </section>

    @if ($recent->isNotEmpty())
        <section class="mt-14">
            <h2 class="text-xl font-semibold">Recently viewed</h2>
            <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 lg:grid-cols-4">@foreach ($recent->take(4) as $p) <x-product-card :product="$p" /> @endforeach</div>
        </section>
    @endif

    @if ($related->isNotEmpty())
        <section class="mt-14">
            <h2 class="text-xl font-semibold">You may also like</h2>
            <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 lg:grid-cols-4">
                @foreach ($related as $p) <x-product-card :product="$p" /> @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
