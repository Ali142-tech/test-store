@php use App\Support\Money; @endphp
@extends('layouts.app')

@section('content')
{{-- ============ Hero ============ --}}
<section class="mesh relative overflow-hidden">
    <div class="container-x grid items-center gap-12 py-16 sm:py-24 lg:grid-cols-[1.05fr_1fr]">
        <div>
            <span class="pill anim-up">● {{ $hero->subtitle ?? 'Retail & wholesale · Priced in PKR' }}</span>
            <h1 class="anim-up delay-1 mt-6 text-5xl font-bold leading-[1.02] tracking-tight sm:text-7xl">
                {{ $hero->title ?? 'Power, sound & charging' }}<br><span class="text-gradient">for every phone.</span>
            </h1>
            <p class="anim-up delay-2 mt-6 max-w-lg text-lg text-ink-soft">Chargers, cables, power banks, earbuds and more from Anker, Samsung, Baseus, Joyroom and other trusted names. Buy one, or buy in bulk for your shop.</p>
            <div class="anim-up delay-3 mt-9 flex flex-wrap gap-3">
                <a href="{{ $hero->cta_url ?? route('shop') }}" class="btn btn-primary !px-8 !py-4 text-base">{{ $hero->cta_label ?? 'Shop now' }} →</a>
                <a href="{{ route('wholesale.register') }}" class="btn btn-ghost !px-8 !py-4 text-base">Wholesale pricing</a>
            </div>
            <dl class="anim-up delay-3 mt-12 grid max-w-md grid-cols-3 gap-6 border-t border-line pt-6">
                <div><dt class="text-xs text-ink-soft">Products</dt><dd class="text-2xl font-bold">{{ $stats['products'] }}</dd></div>
                <div><dt class="text-xs text-ink-soft">Brands</dt><dd class="text-2xl font-bold">{{ $stats['brands'] }}</dd></div>
                <div><dt class="text-xs text-ink-soft">Bulk tiers from</dt><dd class="text-2xl font-bold">10 pcs</dd></div>
            </dl>
        </div>

        @if (! empty($hero?->image))
            <img src="{{ \App\Models\ProductImage::urlFor($hero->image) }}" alt="{{ $hero->title }}" class="w-full rounded-[2rem] object-cover shadow-2xl" fetchpriority="high">
        @else
            <div class="relative mx-auto grid w-full max-w-xl grid-cols-2 gap-5">
                @foreach ($featured->concat($newArrivals)->unique('id')->take(4) as $p)
                    <a href="{{ route('product', $p) }}" class="anim-float group block overflow-hidden rounded-3xl bg-white shadow-xl ring-1 ring-black/5 {{ $loop->odd ? 'translate-y-8' : '' }}" style="--r: {{ $loop->odd ? '-2deg' : '2deg' }}; animation-delay: {{ $loop->index * .5 }}s">
                        <div class="aspect-square bg-mist">@if ($p->primaryImageUrl())<img src="{{ $p->primaryImageUrl() }}" alt="{{ $p->name }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" @if (! $loop->first) loading="lazy" @endif>@endif</div>
                        <div class="p-3"><p class="line-clamp-1 text-xs font-medium">{{ $p->name }}</p><p class="text-sm font-bold">{{ Money::format($p->defaultVariant?->currentRetailPrice()) }}</p></div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- ============ Brand strip ============ --}}
@if ($brands->isNotEmpty())
<section class="marquee overflow-hidden border-y border-line bg-white py-5" aria-label="Brands">
    <div class="marquee-track gap-10 text-lg font-semibold text-ink-soft/70">
        @foreach ([1, 2] as $_)
            @foreach ($brands as $b)<a href="{{ route('brand', $b) }}" class="mx-6 whitespace-nowrap hover:text-ink">{{ $b->name }}</a>@endforeach
        @endforeach
    </div>
</section>
@endif

{{-- ============ Categories ============ --}}
<section class="section container-x">
    <div class="flex items-end justify-between"><div><p class="eyebrow">Browse</p><h2 class="h-section mt-1">Shop by category</h2></div><a href="{{ route('shop') }}" class="text-sm font-medium text-accent">All products →</a></div>
    <div class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
        @foreach ($categories as $c)
            @php $cover = $c->coverUrl(); @endphp
            <a href="{{ route('category', $c) }}" class="card-lift group relative block overflow-hidden rounded-3xl bg-mist ring-1 ring-line">
                <div class="aspect-[4/5]">@if ($cover)<img src="{{ $cover }}" alt="" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">@endif</div>
                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-ink via-ink/85 to-transparent p-4 pt-16 text-white"><p class="font-semibold leading-tight">{{ $c->name }}</p><p class="text-xs text-white/70">{{ $c->active_count }} products</p></div>
            </a>
        @endforeach
    </div>
</section>

{{-- ============ Product rails ============ --}}
@foreach ([['Best sellers', 'Customer favourites', $bestSellers, 'best-sellers'], ['New arrivals', 'Just added', $newArrivals, 'new-arrivals'], ['Featured collection', 'Hand-picked', $featured, 'shop'], ['Deals', 'Limited-time prices', $deals, 'deals'], ['Bundles & combo deals', 'Better together', $bundles, 'bundles']] as [$label, $eyebrow, $list, $route])
    @if ($list->isNotEmpty())
    <section class="section container-x !pt-0">
        <div class="flex items-end justify-between"><div><p class="eyebrow">{{ $eyebrow }}</p><h2 class="h-section mt-1">{{ $label }}</h2></div><a href="{{ route($route) }}" class="text-sm font-medium text-accent">View all →</a></div>
        <div class="mt-8 grid grid-cols-2 gap-x-4 gap-y-8 lg:grid-cols-4">@foreach ($list->take(4) as $p) <x-product-card :product="$p" /> @endforeach</div>
    </section>
    @endif
@endforeach

{{-- ============ Shop by phone ============ --}}
<section class="bg-ink section text-white">
    <div class="container-x">
        <p class="eyebrow !text-white/60">Compatibility</p><h2 class="h-section mt-1">Find accessories for your phone</h2>
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($deviceBrands as $b)
                <div class="rounded-3xl bg-white/5 p-6 ring-1 ring-white/10">
                    <p class="font-semibold">{{ $b->name }}</p>
                    <div class="mt-4 flex flex-wrap gap-2">@foreach ($b->deviceModels as $m)<a class="rounded-full border border-white/15 px-3 py-1.5 text-sm text-white/80 transition hover:border-white hover:bg-white hover:text-ink" href="{{ route('device', [$b, $m]) }}">{{ $m->name }}</a>@endforeach</div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ Wholesale ============ --}}
<section class="section container-x">
    <div class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-accent via-[#7a3df5] to-[#c026d3] p-8 text-white sm:p-14">
        <div class="absolute -right-24 -top-24 h-80 w-80 rounded-full bg-white/10 blur-2xl"></div>
        <p class="eyebrow !text-white/70">For shops & resellers</p>
        <h2 class="mt-3 max-w-xl text-3xl font-bold tracking-tight sm:text-5xl">Buying in bulk? Get tiered wholesale pricing.</h2>
        <ul class="mt-6 grid max-w-2xl gap-2 text-white/90 sm:grid-cols-2">
            <li>✓ Quantity-based price tiers</li><li>✓ Bulk order by SKU or CSV upload</li><li>✓ Request a custom quote</li><li>✓ Wholesale order history</li>
        </ul>
        <div class="mt-8 flex flex-wrap gap-3"><a href="{{ route('wholesale.register') }}" class="btn bg-white !px-7 !py-3.5 text-ink hover:bg-accent-soft">Apply for a wholesale account</a><a href="{{ route('contact') }}" class="btn border border-white/40 !px-7 !py-3.5 text-white hover:bg-white/10">Talk to sales</a></div>
    </div>
</section>

{{-- ============ Trust ============ --}}
<section class="container-x pb-4">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($trust as $title => $text)
            <div class="card-lift rounded-3xl border border-line bg-white p-6"><p class="font-semibold">{{ $title }}</p><p class="mt-2 text-sm text-ink-soft">{{ $text }}</p></div>
        @endforeach
    </div>
</section>

@if ($reviews->isNotEmpty())
<section class="section">
    <div class="container-x">
        <h2 class="h-section">What customers say</h2>
        <div class="mt-8 grid gap-4 md:grid-cols-3">
            @foreach ($reviews as $r)
                <figure class="rounded-3xl bg-mist p-6"><p class="text-amber-500">{{ str_repeat('★', $r->rating) }}</p>
                    <blockquote class="mt-2 text-sm">{{ $r->body }}</blockquote>
                    <figcaption class="mt-3 text-xs text-ink-soft">{{ $r->user->name }} on <a class="underline" href="{{ route('product', $r->product) }}">{{ $r->product->name }}</a>@if ($r->is_verified_purchase) · Verified purchase @endif</figcaption></figure>
            @endforeach
        </div>
    </div>
</section>
@endif

@if ($faqs->isNotEmpty())
<section class="section container-x max-w-3xl">
    <h2 class="h-section text-center">Frequently asked questions</h2>
    <div class="mt-8 divide-y divide-line rounded-3xl border border-line bg-white px-6">
        @foreach ($faqs as $f)
            <details class="group py-5">
                <summary class="flex cursor-pointer list-none items-center justify-between font-medium">{{ $f->question }}<span class="text-xl text-accent transition group-open:rotate-45">+</span></summary>
                <p class="mt-3 text-ink-soft">{{ $f->answer }}</p>
            </details>
        @endforeach
    </div>
</section>
@endif
@endsection
