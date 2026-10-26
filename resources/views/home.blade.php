@extends('layouts.app')

@section('content')
{{-- Hero --}}
<section class="bg-gradient-to-b from-accent-soft/60 to-white">
    <div class="container-x grid items-center gap-10 py-16 sm:py-24 lg:grid-cols-2">
        <div>
            <p class="eyebrow">{{ $hero->subtitle ?? 'Accessories for every phone' }}</p>
            <h1 class="mt-4 text-4xl font-semibold leading-[1.05] tracking-tight sm:text-6xl">{{ $hero->title ?? 'Gear that keeps up with your phone.' }}</h1>
            <p class="mt-5 max-w-lg text-lg text-ink-soft">Cases, chargers, cables and audio for iPhone, Samsung, Pixel and more. Buy one, or buy in bulk for your shop.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ $hero->cta_url ?? route('shop') }}" class="btn btn-primary">{{ $hero->cta_label ?? 'Shop now' }}</a>
                <a href="{{ route('deals') }}" class="btn btn-ghost">View deals</a>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4" aria-hidden="true">
            @foreach ($featured->take(4) as $p)
                <div class="aspect-square rounded-[var(--radius-card)] bg-white shadow-sm border border-line flex items-center justify-center p-4 text-center text-sm font-medium {{ $loop->odd ? 'translate-y-6' : '' }}">{{ $p->name }}</div>
            @endforeach
        </div>
    </div>
</section>

{{-- Categories --}}
<section class="section container-x">
    <h2 class="h-section">Shop by category</h2>
    <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ($categories as $c)
            <a href="{{ route('category', $c) }}" class="rounded-2xl border border-line p-5 transition hover:border-accent hover:bg-accent-soft/40">
                <span class="text-sm font-medium">{{ $c->name }}</span>
            </a>
        @endforeach
    </div>
</section>

{{-- Device brands --}}
<section class="bg-mist section">
    <div class="container-x">
        <h2 class="h-section">Shop by your phone</h2>
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($deviceBrands as $b)
                <div class="rounded-2xl bg-white p-6">
                    <p class="font-semibold">{{ $b->name }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($b->deviceModels as $m)
                            <a class="chip" href="{{ route('device', [$b, $m]) }}">{{ $m->name }}</a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

@foreach ([['Best sellers', $bestSellers, 'best-sellers'], ['New arrivals', $newArrivals, 'new-arrivals'], ['Deals', $deals, 'deals']] as [$label, $list, $route])
    @if ($list->isNotEmpty())
    <section class="section container-x">
        <div class="flex items-end justify-between">
            <h2 class="h-section">{{ $label }}</h2>
            <a href="{{ route($route) }}" class="text-sm font-medium text-accent">View all →</a>
        </div>
        <div class="mt-8 grid grid-cols-2 gap-x-4 gap-y-8 lg:grid-cols-4">
            @foreach ($list->take(4) as $p) <x-product-card :product="$p" /> @endforeach
        </div>
    </section>
    @endif
@endforeach

{{-- Wholesale --}}
<section class="container-x">
    <div class="rounded-[2rem] bg-ink px-6 py-14 text-white sm:px-14">
        <p class="eyebrow !text-white/70">For shops & resellers</p>
        <h2 class="mt-3 max-w-xl text-3xl font-semibold tracking-tight sm:text-4xl">Buying in bulk? Get tiered wholesale pricing.</h2>
        <p class="mt-4 max-w-xl text-white/70">Apply for a business account. Once approved you'll see quantity-based prices, can place bulk orders, and request custom quotes.</p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('wholesale.register') }}" class="btn bg-white text-ink hover:bg-accent-soft">Apply for a wholesale account</a>
            <a href="{{ route('contact') }}" class="btn border border-white/30 text-white">Talk to sales</a>
        </div>
    </div>
</section>

{{-- FAQ --}}
@if ($faqs->isNotEmpty())
<section class="section container-x max-w-3xl">
    <h2 class="h-section text-center">Frequently asked questions</h2>
    <div class="mt-8 divide-y divide-line border-y border-line">
        @foreach ($faqs as $f)
            <details class="group py-4">
                <summary class="flex cursor-pointer list-none items-center justify-between font-medium">{{ $f->question }}<span class="text-accent group-open:rotate-45 transition">+</span></summary>
                <p class="mt-3 text-ink-soft">{{ $f->answer }}</p>
            </details>
        @endforeach
    </div>
</section>
@endif
@endsection
