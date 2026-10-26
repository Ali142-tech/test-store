@props(['product'])
@php
    use App\Support\Money;
    $v = $product->defaultVariant;
    $img = $product->primaryImageUrl();
    $available = $v?->available() ?? 0;
    $onSale = $v?->onSale();
@endphp
<article class="group relative">
    <a href="{{ route('product', $product) }}" class="block">
        <div class="relative aspect-square overflow-hidden rounded-[var(--radius-card)] bg-mist">
            @if ($img)
                <img src="{{ $img }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
            @else
                <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-accent-soft to-mist text-4xl font-semibold text-accent/60" aria-hidden="true">{{ mb_substr($product->name, 0, 1) }}</div>
            @endif
            @if ($onSale) <span class="absolute left-3 top-3 rounded-full bg-accent px-2.5 py-1 text-xs font-semibold text-white">Sale</span> @endif
            @if ($v && $available === 0) <span class="absolute right-3 top-3 rounded-full bg-white px-2.5 py-1 text-xs font-medium text-ink-soft">Out of stock</span> @endif
        </div>
        <p class="mt-3 text-xs text-ink-soft">{{ $product->brand?->name }}</p>
        <h3 class="mt-0.5 text-sm font-medium leading-snug">{{ $product->name }}</h3>
    </a>
    @if (($product->reviews_count ?? 0) > 0)
        <p class="mt-1 text-xs text-ink-soft"><span class="text-amber-500">★</span> {{ number_format($product->reviews_avg_rating, 1) }} ({{ $product->reviews_count }})</p>
    @endif
    @if ($v)
        <p class="mt-1.5 text-sm">
            <span class="font-semibold">{{ Money::format($v->currentRetailPrice()) }}</span>
            @if ($onSale) <span class="ml-1 text-ink-soft line-through">{{ Money::format($v->price) }}</span> @endif
        </p>
    @endif
</article>
