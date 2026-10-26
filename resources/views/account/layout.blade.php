@extends('layouts.app', ['metaTitle' => $metaTitle ?? 'My account'])
@section('content')
<div class="container-x grid gap-10 py-12 lg:grid-cols-[14rem_1fr]">
    <nav class="flex gap-4 overflow-x-auto text-sm lg:flex-col lg:gap-1" aria-label="Account">
        @foreach (['account.dashboard' => 'Overview', 'account.orders' => 'Orders', 'account.wishlist' => 'Wishlist', 'account.profile' => 'Profile & addresses'] as $r => $l)
            <a href="{{ route($r) }}" class="whitespace-nowrap rounded-lg px-3 py-2 {{ request()->routeIs($r) ? 'bg-accent-soft font-semibold text-accent' : 'hover:bg-mist' }}">{{ $l }}</a>
        @endforeach
        @if (auth()->user()->wholesaleApplication)<a href="{{ route('wholesale.dashboard') }}" class="whitespace-nowrap rounded-lg px-3 py-2 hover:bg-mist">Wholesale</a>@endif
        <form method="post" action="{{ route('logout') }}">@csrf<button class="rounded-lg px-3 py-2 text-left text-ink-soft hover:bg-mist">Sign out</button></form>
    </nav>
    <div>@yield('account')</div>
</div>
@endsection
