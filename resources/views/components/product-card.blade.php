@props(['product'])
@php
    use App\Support\Money;
    $v = $product->defaultVariant;
    $img = $product->primaryImageUrl();
    $available = $product->relationLoaded('variants') && $product->variants->isNotEmpty() ? $product->variants->sum(fn ($x) => $x->available()) : ($v?->available() ?? 0);
    $onSale = $v?->onSale();
@endphp
<article class="card-lift group relative rounded-3xl bg-surface p-2 ring-1 ring-line">
    <a href="{{ route('product', $product) }}" class="block">
        <div class="photo-bg relative aspect-square overflow-hidden rounded-[1.25rem] bg-mist">
            @if ($img)
                <img src="{{ $img }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
            @else
                <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-accent-soft to-mist text-4xl font-semibold text-accent/60" aria-hidden="true">{{ mb_substr($product->name, 0, 1) }}</div>
            @endif
            <div class="absolute left-3 top-3 flex gap-1.5">
                @if ($onSale) <span class="rounded-full bg-accent px-2.5 py-1 text-xs font-semibold text-white">Sale</span> @endif
                @if ($product->type === 'bundle') <span class="rounded-full bg-deep px-2.5 py-1 text-xs font-semibold text-white">Bundle</span> @endif
            </div>
            @if ($v && $available === 0) <span class="absolute right-3 top-3 rounded-full bg-white/90 px-2.5 py-1 text-xs font-medium text-ink-soft">Out of stock</span> @endif
        </div>
        <div class="px-2.5 pb-2 pt-3">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-accent">{{ $product->brand?->name }}</p>
            <h3 class="mt-1 line-clamp-2 min-h-[2.5rem] text-sm font-medium leading-snug">{{ $product->name }}</h3>
            @if (($product->reviews_count ?? 0) > 0)
                <p class="mt-1 text-xs text-ink-soft"><span class="text-amber-500">★</span> {{ number_format($product->reviews_avg_rating, 1) }} ({{ $product->reviews_count }})</p>
            @endif
            @php $colors = $product->colorOptions(); @endphp
            @if ($colors->count() > 1)
                <div class="mt-2 flex items-center gap-1" aria-label="{{ $colors->count() }} colours">
                    @foreach ($colors->take(5) as $c)<span title="{{ $c }}" class="h-3.5 w-3.5 rounded-full ring-1 ring-black/15" style="background: {{ \App\Support\ColorMap::css($c) ?? '#e5e7eb' }}"></span>@endforeach
                    @if ($colors->count() > 5)<span class="text-xs text-ink-soft">+{{ $colors->count() - 5 }}</span>@endif
                </div>
            @endif
            @if ($v)
                <p class="mt-2 flex items-baseline gap-2">
                    <span class="text-lg font-bold">{{ Money::unit($v->currentRetailPrice()) }}</span>
                    @if ($onSale) <span class="text-sm text-ink-soft line-through">{{ Money::unit($v->price) }}</span> @endif
                </p>
            @endif
        </div>
    </a>
</article>
