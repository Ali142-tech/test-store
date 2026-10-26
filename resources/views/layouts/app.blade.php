@php
    use App\Models\Category;
    use App\Models\StoreSetting;
    $storeName = StoreSetting::get('store_name', config('app.name'));
    $navCategories = Category::where('is_active', true)->whereNull('parent_id')->orderBy('sort_order')->limit(8)->get();
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($metaTitle) ? $metaTitle.' | '.$storeName : $storeName.' - Mobile accessories, retail & wholesale' }}</title>
    <meta name="description" content="{{ $metaDescription ?? 'Phone cases, chargers, cables, power banks and more for every major phone brand. Retail and wholesale.' }}">
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    <meta property="og:title" content="{{ $metaTitle ?? $storeName }}">
    <meta property="og:description" content="{{ $metaDescription ?? '' }}">
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    @if (! empty($ogImage)) <meta property="og:image" content="{{ $ogImage }}"> @endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body x-data="{ menu: false }" class="min-h-screen flex flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-3">Skip to content</a>

    @if ($announcement = StoreSetting::get('announcement'))
        <div class="bg-ink text-center text-xs sm:text-sm text-white py-2 px-4">{{ $announcement }}</div>
    @endif

    <header class="sticky top-0 z-40 border-b border-line bg-white/90 backdrop-blur">
        <div class="container-x flex h-16 items-center gap-4">
            <button class="lg:hidden -ml-2 p-2" @click="menu = true" aria-label="Open menu">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <a href="{{ route('home') }}" class="text-xl font-bold tracking-tight">{{ $storeName }}<span class="text-accent">.</span></a>

            <nav class="ml-6 hidden lg:flex items-center gap-6 text-sm font-medium" aria-label="Main">
                <a href="{{ route('shop') }}" class="hover:text-accent">Shop</a>
                @foreach ($navCategories->take(4) as $c)
                    <a href="{{ route('category', $c) }}" class="hover:text-accent">{{ $c->name }}</a>
                @endforeach
                <a href="{{ route('deals') }}" class="text-accent">Deals</a>
            </nav>

            <form action="{{ route('search') }}" method="get" class="ml-auto flex-1 max-w-md" role="search">
                <label class="sr-only" for="q">Search</label>
                <input id="q" name="q" value="{{ request('q') }}" type="search" placeholder="Search products, SKU or phone model"
                       class="field !rounded-full !py-2.5 bg-mist border-transparent">
            </form>
        </div>
    </header>

    {{-- Mobile drawer --}}
    <div x-cloak x-show="menu" class="fixed inset-0 z-50 lg:hidden" @keydown.escape.window="menu = false">
        <div class="absolute inset-0 bg-black/40" @click="menu = false"></div>
        <aside class="absolute left-0 top-0 h-full w-80 max-w-[85%] bg-white p-6 overflow-y-auto" x-transition>
            <div class="flex justify-between items-center mb-6">
                <span class="text-lg font-bold">{{ $storeName }}</span>
                <button @click="menu = false" aria-label="Close menu" class="p-2">✕</button>
            </div>
            <nav class="grid gap-1 text-base">
                <a class="py-3" href="{{ route('shop') }}">Shop all</a>
                @foreach ($navCategories as $c)
                    <a class="py-3 text-ink-soft" href="{{ route('category', $c) }}">{{ $c->name }}</a>
                @endforeach
                <a class="py-3 font-semibold text-accent" href="{{ route('deals') }}">Deals</a>
                <a class="py-3" href="{{ route('new-arrivals') }}">New arrivals</a>
                <a class="py-3" href="{{ route('best-sellers') }}">Best sellers</a>
            </nav>
        </aside>
    </div>

    <main id="main" class="flex-1">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <footer class="mt-20 border-t border-line bg-mist">
        <div class="container-x grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-4 text-sm">
            <div>
                <p class="text-lg font-bold">{{ $storeName }}<span class="text-accent">.</span></p>
                <p class="mt-3 text-ink-soft">Accessories for every major phone brand. Retail and wholesale.</p>
            </div>
            <div>
                <p class="font-semibold mb-3">Shop</p>
                <ul class="space-y-2 text-ink-soft">
                    <li><a href="{{ route('shop') }}">All products</a></li>
                    <li><a href="{{ route('new-arrivals') }}">New arrivals</a></li>
                    <li><a href="{{ route('best-sellers') }}">Best sellers</a></li>
                    <li><a href="{{ route('deals') }}">Deals</a></li>
                </ul>
            </div>
            <div>
                <p class="font-semibold mb-3">Help</p>
                <ul class="space-y-2 text-ink-soft">
                    <li><a href="{{ route('faqs') }}">FAQs</a></li>
                    <li><a href="{{ route('page', 'shipping-policy') }}">Shipping policy</a></li>
                    <li><a href="{{ route('page', 'returns-and-refunds') }}">Returns & refunds</a></li>
                    <li><a href="{{ route('page', 'warranty-policy') }}">Warranty policy</a></li>
                </ul>
            </div>
            <div>
                <p class="font-semibold mb-3">Company</p>
                <ul class="space-y-2 text-ink-soft">
                    <li><a href="{{ route('page', 'about') }}">About us</a></li>
                    <li><a href="{{ route('page', 'privacy-policy') }}">Privacy policy</a></li>
                    <li><a href="{{ route('page', 'terms-and-conditions') }}">Terms & conditions</a></li>
                    @if ($email = StoreSetting::get('contact_email')) <li>{{ $email }}</li> @endif
                </ul>
            </div>
        </div>
        <div class="border-t border-line py-5 text-center text-xs text-ink-soft">© {{ date('Y') }} {{ $storeName }}. All rights reserved.</div>
    </footer>
</body>
</html>
