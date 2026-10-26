@php
    use App\Models\Category;
    use App\Models\StoreSetting;
    $storeName = StoreSetting::get('store_name', config('app.name'));
    $navCategories = \Illuminate\Support\Facades\Cache::remember('nav.categories', 600, fn () => Category::visible()->whereNull('parent_id')->orderBy('sort_order')->limit(8)->get());
    $cartCount = app(\App\Services\Cart\CartService::class)->count(auth()->user());
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($metaTitle) ? $metaTitle.' | '.$storeName : $storeName.' - Mobile accessories, retail & wholesale' }}</title>
    <meta name="description" content="{{ $metaDescription ?? 'Phone cases, chargers, cables, power banks and more for every major phone brand. Retail and wholesale.' }}">
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    @if (request()->routeIs('cart.*', 'checkout.*', 'order.*', 'account.*', 'wholesale.*', 'quotes.*', 'login', 'register', 'search')) <meta name="robots" content="noindex,follow"> @endif
    <meta property="og:title" content="{{ $metaTitle ?? $storeName }}">
    <meta property="og:description" content="{{ $metaDescription ?? '' }}">
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    @if (! empty($ogImage)) <meta property="og:image" content="{{ $ogImage }}"> @endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body x-data="{ menu: false, search: false }" class="min-h-screen flex flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-3">Skip to content</a>

    @if ($announcement = StoreSetting::get('announcement'))
        <div class="bg-gradient-to-r from-ink via-[#241a52] to-ink text-center text-xs sm:text-sm text-white py-2.5 px-4 tracking-wide">{{ $announcement }}</div>
    @endif

    <header class="glass sticky top-0 z-40 border-b border-line/70">
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

            <form action="{{ route('search') }}" method="get" class="ml-auto hidden max-w-md flex-1 md:block" role="search">
                <label class="sr-only" for="q">Search</label>
                <input id="q" name="q" value="{{ request('q') }}" type="search" placeholder="Search products, SKU or phone model"
                       class="field !rounded-full !py-2.5 bg-mist border-transparent">
            </form>

            <div class="ml-auto flex items-center gap-0.5 text-sm font-medium md:ml-0">
                <button type="button" class="p-2 md:hidden" @click="search = !search" :aria-expanded="search" aria-label="Search">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></button>
                @auth
                    <a href="{{ route('account.wishlist') }}" class="hidden sm:inline-flex p-2" aria-label="Wishlist">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 21s-7-4.5-9.5-9A5.5 5.5 0 0 1 12 6a5.5 5.5 0 0 1 9.5 6c-2.5 4.5-9.5 9-9.5 9z"/></svg></a>
                    <a href="{{ route('account.dashboard') }}" class="p-2" aria-label="My account">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg></a>
                @else
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 p-2 sm:px-3" aria-label="Sign in">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg><span class="hidden sm:inline">Sign in</span></a>
                @endauth
                <a href="{{ route('cart.show') }}" class="relative p-2" aria-label="Cart ({{ $cartCount }} items)">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M5 7h14l-1.5 11h-11zM9 7a3 3 0 0 1 6 0"/></svg>
                    @if ($cartCount > 0)<span class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-accent px-1 text-[11px] font-semibold text-white">{{ $cartCount }}</span>@endif
                </a>
            </div>
        </div>
    </header>
    <div x-cloak x-show="search" x-transition class="border-b border-line bg-white md:hidden">
        <form action="{{ route('search') }}" method="get" role="search" class="container-x flex gap-2 py-3">
            <label class="sr-only" for="q-m">Search</label>
            <input id="q-m" name="q" value="{{ request('q') }}" type="search" placeholder="Search products, SKU or phone model" class="field !rounded-full !py-2.5" x-effect="if (search) $nextTick(() => $el.focus())">
            <button class="btn btn-primary !px-5">Search</button>
        </form>
    </div>

    {{-- Mobile drawer --}}
    <div x-cloak x-show="menu" class="fixed inset-0 z-50 lg:hidden" @keydown.escape.window="menu = false">
        <div class="absolute inset-0 bg-black/40" @click="menu = false"></div>
        <aside class="absolute left-0 top-0 h-full w-80 max-w-[85%] bg-white p-6 overflow-y-auto" x-transition>
            <div class="flex justify-between items-center mb-6">
                <span class="text-lg font-bold">{{ $storeName }}</span>
                <button @click="menu = false" aria-label="Close menu" class="p-2">✕</button>
            </div>
            <div class="mb-4 grid gap-2 rounded-2xl bg-mist p-4 text-sm">
                @auth
                    <p class="font-semibold">{{ auth()->user()->name }}</p>
                    <a class="py-1" href="{{ route('account.dashboard') }}">My account</a>
                    <a class="py-1" href="{{ route('account.orders') }}">My orders</a>
                    <a class="py-1" href="{{ route('account.wishlist') }}">Wishlist</a>
                    <form method="post" action="{{ route('logout') }}">@csrf<button class="py-1 text-left text-ink-soft">Sign out</button></form>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary w-full">Sign in</a>
                    <a href="{{ route('register') }}" class="btn btn-ghost w-full">Create account</a>
                @endauth
            </div>
            <nav class="grid gap-1 text-base">
                <a class="py-3" href="{{ route('shop') }}">Shop all</a>
                @foreach ($navCategories as $c)
                    <a class="py-3 text-ink-soft" href="{{ route('category', $c) }}">{{ $c->name }}</a>
                @endforeach
                <a class="py-3 font-semibold text-accent" href="{{ route('deals') }}">Deals</a>
                <a class="py-3" href="{{ route('new-arrivals') }}">New arrivals</a>
                <a class="py-3" href="{{ route('best-sellers') }}">Best sellers</a>
                <hr class="my-2 border-line">
                <a class="py-3 text-ink-soft" href="{{ route('wholesale.register') }}">Wholesale accounts</a>
                <a class="py-3 text-ink-soft" href="{{ route('order.track') }}">Track an order</a>
                <a class="py-3 text-ink-soft" href="{{ route('contact') }}">Contact us</a>
            </nav>
        </aside>
    </div>

    <main id="main" class="flex-1">
        @if (session('status'))
            <div class="container-x pt-6"><p class="rounded-xl bg-accent-soft p-3 text-sm" role="status">{{ session('status') }}</p></div>
        @endif
        @yield('content')
    </main>

    <section class="mt-16 border-t border-line bg-mist">
        <div class="container-x flex flex-col items-start justify-between gap-6 py-12 md:flex-row md:items-center">
            <div><h2 class="text-xl font-semibold">Get offers and new arrivals</h2><p class="text-sm text-ink-soft">Marketing emails only with your consent. Unsubscribe anytime.</p></div>
            <form action="{{ route('newsletter.store') }}" method="post" class="flex w-full max-w-md gap-2">
                @csrf
                <label class="sr-only" for="nl-email">Email</label>
                <input id="nl-email" name="email" type="email" required placeholder="you@example.com" class="field">
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
                <button class="btn btn-primary">Subscribe</button>
            </form>
        </div>
    </section>

    <footer class="bg-ink text-white">
        <div class="container-x grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-4 text-sm">
            <div>
                <p class="text-lg font-bold">{{ $storeName }}<span class="text-accent">.</span></p>
                <p class="mt-3 text-white/60">Accessories for every major phone brand. Retail and wholesale.</p>
            </div>
            <div>
                <p class="font-semibold mb-3">Shop</p>
                <ul class="space-y-2 text-white/60 [&_a:hover]:text-white">
                    <li><a href="{{ route('shop') }}">All products</a></li>
                    <li><a href="{{ route('new-arrivals') }}">New arrivals</a></li>
                    <li><a href="{{ route('best-sellers') }}">Best sellers</a></li>
                    <li><a href="{{ route('deals') }}">Deals</a></li>
                </ul>
            </div>
            <div>
                <p class="font-semibold mb-3">Help</p>
                <ul class="space-y-2 text-white/60 [&_a:hover]:text-white">
                    <li><a href="{{ route('faqs') }}">FAQs</a></li>
                    <li><a href="{{ route('contact') }}">Contact us</a></li>
                    <li><a href="{{ route('order.track') }}">Track an order</a></li>
                    <li><a href="{{ route('wholesale.register') }}">Wholesale accounts</a></li>
                    <li><a href="{{ route('page', 'shipping-policy') }}">Shipping policy</a></li>
                    <li><a href="{{ route('page', 'returns-and-refunds') }}">Returns & refunds</a></li>
                    <li><a href="{{ route('page', 'warranty-policy') }}">Warranty policy</a></li>
                </ul>
            </div>
            <div>
                <p class="font-semibold mb-3">Company</p>
                <ul class="space-y-2 text-white/60 [&_a:hover]:text-white">
                    <li><a href="{{ route('page', 'about') }}">About us</a></li>
                    <li><a href="{{ route('page', 'privacy-policy') }}">Privacy policy</a></li>
                    <li><a href="{{ route('page', 'terms-and-conditions') }}">Terms & conditions</a></li>
                    <li><a href="{{ url('/admin/login') }}">Admin login</a></li>
                    @if ($email = StoreSetting::get('contact_email')) <li>{{ $email }}</li> @endif
                </ul>
            </div>
        </div>
        <div class="border-t border-white/10 py-5 text-center text-xs text-white/50">© {{ date('Y') }} {{ $storeName }}. All rights reserved.</div>
    </footer>
</body>
</html>
