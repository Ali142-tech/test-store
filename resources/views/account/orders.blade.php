@php use App\Support\Money; @endphp
@extends('account.layout', ['metaTitle' => 'My orders'])
@section('account')
<h1 class="text-2xl font-semibold">Orders</h1>
@forelse ($orders as $o)
    <a href="{{ route('account.orders.show', $o->reference) }}" class="mt-3 flex justify-between rounded-xl border border-line p-4 text-sm hover:border-accent">
        <span>{{ $o->reference }}<span class="block text-xs text-ink-soft">{{ $o->placed_at?->format('d M Y') }} · {{ ucfirst($o->status) }} · {{ str_replace('_', ' ', $o->payment_status) }}</span></span>
        <span class="font-medium">{{ Money::order($o->grand_total, $o) }}</span></a>
@empty <p class="mt-4 text-sm text-ink-soft">You have not placed any orders yet.</p> @endforelse
<div class="mt-6">{{ $orders->links() }}</div>
@endsection
