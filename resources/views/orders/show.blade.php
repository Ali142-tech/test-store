@php use App\Support\Money; @endphp
@extends('layouts.app', ['metaTitle' => 'Order '.$order->reference])

@section('content')
<div class="container-x max-w-4xl py-12">
    @if (session('order_access.'.$order->reference) && $order->status === 'pending')
        <div class="mb-8 rounded-2xl bg-accent-soft p-6">
            <h1 class="text-2xl font-semibold">Thank you! Your order is placed.</h1>
            <p class="mt-1 text-sm">A confirmation has been sent to {{ $order->email }}.</p>
        </div>
    @else
        <h1 class="text-2xl font-semibold">Order {{ $order->reference }}</h1>
    @endif

    <div class="mt-4 flex flex-wrap gap-2 text-sm">
        <span class="chip">Status: {{ ucfirst($order->status) }}</span>
        <span class="chip">Payment: {{ str_replace('_', ' ', ucfirst($order->payment_status)) }} ({{ str_replace('_', ' ', $order->payment_method) }})</span>
        <span class="chip">Placed {{ $order->placed_at?->format('d M Y, H:i') }}</span>
    </div>

    @if ($order->payment_method === 'bank_transfer' && $order->payment_status === 'pending')
        <div class="mt-6 rounded-2xl border border-line p-5 text-sm">
            <p class="font-semibold">Complete your bank transfer</p>
            <p class="mt-1 text-ink-soft">Transfer {{ Money::order($order->grand_total, $order) }} and use <strong>{{ $order->reference }}</strong> as the payment reference. We will confirm your order once the payment is verified.</p>
            <dl class="mt-3 space-y-1">@foreach ($bank as $label => $value)<div class="flex flex-wrap gap-x-2"><dt class="text-ink-soft">{{ $label }}:</dt><dd class="min-w-0 break-all">{{ $value }}</dd></div>@endforeach</dl>
        </div>
    @endif

    <table class="mt-8 w-full text-sm">
        <thead class="border-b border-line text-left text-ink-soft"><tr><th class="py-2">Item</th><th>Qty</th><th class="text-right">Unit</th><th class="text-right">Total</th></tr></thead>
        <tbody class="divide-y divide-line">
        @foreach ($order->items as $i)
            <tr><td class="py-3">{{ $i->product_name }}@if ($i->variant_name) <span class="text-ink-soft">({{ $i->variant_name }})</span>@endif<span class="block text-xs text-ink-soft">SKU {{ $i->sku }}</span></td>
                <td>{{ $i->quantity }}</td><td class="text-right">{{ Money::order($i->unit_price, $order) }}</td><td class="text-right">{{ Money::order($i->line_total, $order) }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <dl class="ml-auto mt-6 w-full min-w-0 max-w-xs space-y-1 text-sm">
        <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ Money::order($order->subtotal, $order) }}</dd></div>
        @if ((float) $order->discount_total > 0)<div class="flex justify-between"><dt>Discount</dt><dd>-{{ Money::order($order->discount_total, $order) }}</dd></div>@endif
        <div class="flex justify-between gap-3"><dt class="min-w-0 break-words">Shipping ({{ $order->shipping_method_name }})</dt><dd class="shrink-0">{{ Money::order($order->shipping_total, $order) }}</dd></div>
        @if ((float) $order->tax_total > 0)<div class="flex justify-between"><dt>Tax</dt><dd>{{ Money::order($order->tax_total, $order) }}</dd></div>@endif
        <div class="flex justify-between border-t border-line pt-2 text-base font-semibold"><dt>Total</dt><dd>{{ Money::order($order->grand_total, $order) }}</dd></div>
        @if ((float) $order->refunded_total > 0)<div class="flex justify-between text-ink-soft"><dt>Refunded</dt><dd>{{ Money::order($order->refunded_total, $order) }}</dd></div>@endif
    </dl>

    <div class="mt-8 grid gap-6 text-sm sm:grid-cols-2">
        <div><p class="font-semibold">Shipping address</p><p class="mt-1 text-ink-soft">{{ $order->shipping_address['full_name'] }}<br>{{ $order->shipping_address['line1'] }}<br>{{ $order->shipping_address['city'] }}<br>{{ $order->shipping_address['phone'] }}</p></div>
        <div><p class="font-semibold">Shipments</p>
            @forelse ($order->shipments as $s)
                <p class="mt-1 text-ink-soft">{{ $s->courier }} · {{ $s->tracking_number }} · {{ ucfirst(str_replace('_', ' ', $s->status)) }} @if ($s->tracking_url)<a class="text-accent underline" href="{{ $s->tracking_url }}" rel="noopener" target="_blank">Track</a>@endif</p>
            @empty <p class="mt-1 text-ink-soft">Not shipped yet.</p> @endforelse
        </div>
    </div>

    @if ($order->status === 'delivered' && ($account ?? false))
        <details class="mt-8 rounded-2xl border border-line p-5 text-sm">
            <summary class="cursor-pointer font-semibold">Request a return</summary>
            <form method="post" action="{{ route('account.orders.return', $order->reference) }}" class="mt-4 grid gap-3 sm:grid-cols-3">@csrf
                <select name="order_item_id" class="field">@foreach ($order->items as $i)<option value="{{ $i->id }}">{{ $i->product_name }}</option>@endforeach</select>
                <input name="quantity" type="number" min="1" value="1" class="field" aria-label="Quantity">
                <input name="reason" class="field" placeholder="Reason" required>
                <button class="btn btn-ghost sm:col-span-3">Submit return request</button>
            </form>
            @error('quantity')<p class="mt-2 text-danger">{{ $message }}</p>@enderror
        </details>
        <details class="mt-4 rounded-2xl border border-line p-5 text-sm">
            <summary class="cursor-pointer font-semibold">Review a product</summary>
            @foreach ($order->items->unique('product_id') as $i)
                @php $slug = \App\Models\Product::whereKey($i->product_id)->value('slug'); @endphp
                @if ($slug)
                <form method="post" action="{{ route('account.reviews.store', $slug) }}" class="mt-4 grid gap-3 sm:grid-cols-4">@csrf
                    <span class="self-center">{{ $i->product_name }}</span>
                    <select name="rating" class="field" aria-label="Rating">@for ($r = 5; $r >= 1; $r--)<option value="{{ $r }}">{{ $r }} ★</option>@endfor</select>
                    <input name="body" class="field sm:col-span-1" placeholder="Your review">
                    <button class="btn btn-ghost">Submit</button>
                </form>
                @endif
            @endforeach
        </details>
    @endif

    <p class="mt-10 flex gap-4 text-sm">
        <a class="text-accent underline" href="{{ ($account ?? false) ? route('account.orders.invoice', $order->reference) : \Illuminate\Support\Facades\URL::temporarySignedRoute('order.invoice', now()->addMinutes(30), $order->reference) }}" target="_blank">Printable invoice</a>
        <a class="text-accent underline" href="{{ route('shop') }}">Continue shopping</a>
    </p>
</div>
@endsection
