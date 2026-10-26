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
    'aggregateRating' => $product->reviews_count > 0 ? ['@type' => 'AggregateRating', 'ratingValue' => round($product->reviews_avg_rating, 1), 'reviewCount' => $product->reviews_count] : null,
]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
</script>
@endpush

@section('content')
<div class="container-x py-10" x-data='{
    variants: @js($variants),
    selected: 0,
    qty: 1,
    get v() { return this.variants[this.selected] },
    get minQty() { return this.v ? this.v.moq : 1 },
    init() { this.qty = this.minQty },
    pick(i) { this.selected = i; this.qty = Math.max(this.qty, this.minQty); this.qty = Math.min(this.qty, Math.max(this.v.available, 1)) },
    money(n) { return "{{ \App\Models\StoreSetting::get("currency_symbol", "Rs") }} " + Number(n).toLocaleString("en-US", {maximumFractionDigits: 0}) }
}'>
    <nav class="text-xs text-ink-soft" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a>
        @if ($product->category) / <a href="{{ route('category', $product->category) }}">{{ $product->category->name }}</a> @endif
        / <span>{{ $product->name }}</span>
    </nav>

    <div class="mt-6 grid gap-10 lg:grid-cols-2">
        <div x-data="{ i: 0 }">
            @php $imgs = $product->images; @endphp
            <div class="aspect-square overflow-hidden rounded-[var(--radius-card)] bg-mist">
                @forelse ($imgs as $k => $img)
                    <img x-show="i === {{ $k }}" src="{{ $img->url() }}" alt="{{ $img->alt ?: $product->name }}" class="h-full w-full object-cover">
                @empty
                    <div class="flex h-full items-center justify-center bg-gradient-to-br from-accent-soft to-mist text-7xl font-semibold text-accent/50" aria-hidden="true">{{ mb_substr($product->name, 0, 1) }}</div>
                @endforelse
            </div>
            @if ($imgs->count() > 1)
                <div class="mt-3 flex gap-2">
                    @foreach ($imgs as $k => $img)
                        <button @click="i = {{ $k }}" class="h-16 w-16 overflow-hidden rounded-xl border" :class="i === {{ $k }} ? 'border-accent' : 'border-line'"><img src="{{ $img->url() }}" alt="" class="h-full w-full object-cover" loading="lazy"></button>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <p class="text-sm text-ink-soft">{{ $product->brand?->name }}</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight">{{ $product->name }}</h1>
            @if ($product->reviews_count > 0)
                <p class="mt-2 text-sm"><span class="text-amber-500">★</span> {{ number_format($product->reviews_avg_rating, 1) }} · {{ $product->reviews_count }} reviews</p>
            @endif

            <div class="mt-5 flex items-baseline gap-3">
                <span class="text-3xl font-semibold" x-text="money(v.price)"></span>
                <span class="text-lg text-ink-soft line-through" x-show="Number(v.price) < Number(v.regular_price)" x-text="money(v.regular_price)"></span>
                <span class="rounded-full bg-accent-soft px-2.5 py-1 text-xs font-semibold text-accent" x-show="Number(v.price) < Number(v.regular_price)"
                      x-text="Math.round((1 - v.price / v.regular_price) * 100) + '% off'"></span>
            </div>
            <p class="mt-3 text-ink-soft">{{ $product->short_description }}</p>

            @if ($variants->count() > 1)
                <fieldset class="mt-6">
                    <legend class="text-sm font-semibold">Option</legend>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <template x-for="(vr, idx) in variants" :key="vr.id">
                            <button type="button" @click="pick(idx)" class="chip" :class="selected === idx ? '!border-accent bg-accent-soft' : ''" :disabled="vr.available === 0" x-text="vr.name"></button>
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
                    <button type="button" class="h-11 w-11" @click="qty = Math.max(minQty, qty - 1)" aria-label="Decrease">−</button>
                    <input type="number" name="quantity" x-model.number="qty" :min="minQty" :max="v.available" class="w-14 border-0 bg-transparent text-center focus:outline-none" aria-label="Quantity">
                    <button type="button" class="h-11 w-11" @click="qty = Math.min(v.available, qty + 1)" aria-label="Increase">+</button>
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
                <p class="mt-1 text-xs text-ink-soft">{{ $r->user->name }} @if ($r->is_verified_purchase) · Verified purchase @endif</p>
            </div>
        @empty
            <p class="mt-3 text-sm text-ink-soft">No reviews yet.</p>
        @endforelse
    </section>

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
