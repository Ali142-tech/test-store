@php use App\Support\Money; @endphp
@extends('layouts.app', ['metaTitle' => 'Quote '.$quote->reference])
@section('content')
<div class="container-x max-w-3xl py-12">
    <h1 class="text-2xl font-semibold">Quote {{ $quote->reference }}</h1>
    <p class="mt-2"><span class="chip">{{ ucfirst($quote->status) }}</span>
        @if ($quote->expires_at && in_array($quote->status, ['quoted', 'accepted'])) <span class="ml-2 text-sm text-ink-soft">Valid until {{ $quote->expires_at->format('d M Y') }}</span>@endif</p>

    <table class="mt-6 w-full text-sm">
        <thead class="border-b border-line text-left text-ink-soft"><tr><th class="py-2">Product</th><th>Qty</th><th>Target</th><th class="text-right">Quoted unit</th></tr></thead>
        <tbody class="divide-y divide-line">
        @foreach ($quote->items as $i)
            <tr><td class="py-3">{{ $i->variant->product->name }} <span class="text-ink-soft">{{ $i->variant->name }}</span><span class="block text-xs text-ink-soft">{{ $i->variant->sku }}</span></td>
                <td>{{ $i->quantity }}</td><td>{{ $i->target_price ? Money::format($i->target_price) : '-' }}</td>
                <td class="text-right">{{ $i->quoted_price !== null ? Money::format($i->quoted_price) : 'Awaiting quote' }}</td></tr>
        @endforeach
        </tbody>
    </table>
    @if ($quote->admin_notes)<p class="mt-4 rounded-xl bg-mist p-4 text-sm"><strong>Note from our team:</strong> {{ $quote->admin_notes }}</p>@endif

    <div class="mt-6 flex gap-3">
        @if ($quote->status === 'quoted')
            <form method="post" action="{{ route('quotes.accept', $quote->reference) }}">@csrf<button class="btn btn-primary">Accept quote</button></form>
        @endif
        @if ($quote->status === 'accepted')<a href="{{ route('quotes.checkout', $quote->reference) }}" class="btn btn-primary">Proceed to checkout</a>@endif
        @if (in_array($quote->status, ['requested', 'quoted']))
            <form method="post" action="{{ route('quotes.reject', $quote->reference) }}">@csrf<button class="btn btn-ghost">Decline</button></form>
        @endif
    </div>

    <h2 class="mt-10 font-semibold">History</h2>
    <ul class="mt-3 space-y-1 text-sm text-ink-soft">@foreach ($quote->events->sortByDesc('id') as $e)<li>{{ $e->created_at->format('d M Y H:i') }} · {{ ucfirst($e->event) }} @if ($e->note) - {{ $e->note }}@endif</li>@endforeach</ul>
</div>
@endsection
