@extends('layouts.app', ['metaTitle' => $title, 'metaDescription' => $intro ?? "Browse {$title}"])

@section('content')
<div class="container-x py-10" x-data="{ filters: false }">
    <nav class="text-xs text-ink-soft" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a> / <span>{{ $title }}</span></nav>
    <h1 class="mt-3 text-3xl font-semibold tracking-tight">{{ isset($filters['q']) ? 'Results for "'.$filters['q'].'"' : $title }}</h1>
    @if ($intro) <p class="mt-2 max-w-2xl text-ink-soft">{{ $intro }}</p> @endif

    <div class="mt-8 flex items-center justify-between gap-3">
        <button class="btn btn-ghost lg:hidden" @click="filters = !filters">Filters</button>
        <p class="hidden text-sm text-ink-soft sm:block">{{ $products->total() }} products</p>
        <form method="get" class="ml-auto">
            @foreach (request()->except(['sort', 'page']) as $k => $v) @if (is_scalar($v)) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif @endforeach
            <label class="sr-only" for="sort">Sort</label>
            <select id="sort" name="sort" class="field !w-auto !py-2" onchange="this.form.submit()">
                @foreach (['relevance' => 'Relevance', 'newest' => 'Newest', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'popular' => 'Popularity', 'rating' => 'Rating'] as $k => $label)
                    <option value="{{ $k }}" @selected(($filters['sort'] ?? 'relevance') === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="mt-6 grid gap-8 lg:grid-cols-[16rem_1fr]">
        <form method="get" class="space-y-5 text-sm lg:block" :class="filters ? 'block' : 'hidden'">
            @if (request('q')) <input type="hidden" name="q" value="{{ request('q') }}"> @endif
            <div><label class="font-semibold" for="category">Category</label>
                <select id="category" name="category" class="field mt-2"><option value="">All</option>
                    @foreach ($categories as $c) <option value="{{ $c->slug }}" @selected(($filters['category'] ?? '') === $c->slug)>{{ $c->name }}</option> @endforeach
                </select></div>
            <div><label class="font-semibold" for="brand">Brand</label>
                <select id="brand" name="brand" class="field mt-2"><option value="">All</option>
                    @foreach ($brands as $b) <option value="{{ $b->slug }}" @selected(($filters['brand'] ?? '') === $b->slug)>{{ $b->name }}</option> @endforeach
                </select></div>
            <div><label class="font-semibold" for="color">Color</label>
                <select id="color" name="color" class="field mt-2"><option value="">Any</option>
                    @foreach ($colors as $c) <option @selected(($filters['color'] ?? '') === $c)>{{ $c }}</option> @endforeach
                </select></div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="font-semibold" for="min_price">Min price</label><input id="min_price" name="min_price" type="number" min="0" value="{{ $filters['min_price'] ?? '' }}" class="field mt-2"></div>
                <div><label class="font-semibold" for="max_price">Max price</label><input id="max_price" name="max_price" type="number" min="0" value="{{ $filters['max_price'] ?? '' }}" class="field mt-2"></div>
            </div>
            <label class="flex items-center gap-2"><input type="checkbox" name="in_stock" value="1" @checked(! empty($filters['in_stock']))> In stock only</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="on_sale" value="1" @checked(! empty($filters['on_sale']))> On sale</label>
            <div class="flex gap-2">
                <button class="btn btn-primary flex-1">Apply</button>
                <a href="{{ url()->current() }}" class="btn btn-ghost">Reset</a>
            </div>
        </form>

        <div>
            @forelse ($products as $p)
                @if ($loop->first) <div class="grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-3"> @endif
                <x-product-card :product="$p" />
                @if ($loop->last) </div> @endif
            @empty
                <div class="rounded-2xl border border-dashed border-line p-12 text-center">
                    <p class="font-medium">No products match your filters.</p>
                    <p class="mt-1 text-sm text-ink-soft">Try removing a filter or searching for something else.</p>
                    <a href="{{ route('shop') }}" class="btn btn-ghost mt-5">Browse all products</a>
                </div>
            @endforelse
            <div class="mt-10">{{ $products->links() }}</div>
        </div>
    </div>
</div>
@endsection
