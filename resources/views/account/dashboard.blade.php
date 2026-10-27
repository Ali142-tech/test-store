@php use App\Support\Money; @endphp
@extends('account.layout')
@section('account')
<h1 class="text-2xl font-semibold">Hello, {{ auth()->user()->name }}</h1>
<div class="mt-6 grid gap-4 sm:grid-cols-3 text-sm">
    <div class="rounded-2xl bg-mist p-5"><p class="text-ink-soft">Orders</p><p class="text-2xl font-semibold">{{ $orderCount }}</p></div>
    <div class="rounded-2xl bg-mist p-5"><p class="text-ink-soft">Wishlist</p><p class="text-2xl font-semibold">{{ $wishlistCount }}</p></div>
    <div class="rounded-2xl bg-mist p-5"><p class="text-ink-soft">Account type</p><p class="text-2xl font-semibold">{{ ucfirst(auth()->user()->customer_type) }}</p></div>
</div>
<h2 class="mt-10 font-semibold">Recent orders</h2>
@forelse ($recent as $o)
    <a href="{{ route('account.orders.show', $o->reference) }}" class="mt-3 flex justify-between rounded-xl border border-line p-4 text-sm hover:border-accent"><span>{{ $o->reference }} · {{ ucfirst($o->status) }}</span><span>{{ Money::order($o->grand_total, $o) }}</span></a>
@empty <p class="mt-3 text-sm text-ink-soft">No orders yet. <a class="text-accent" href="{{ route('shop') }}">Start shopping</a>.</p> @endforelse
@endsection
