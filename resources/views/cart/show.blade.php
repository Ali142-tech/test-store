@php use App\Support\Money; @endphp
@extends('layouts.app', ['metaTitle' => 'Your cart'])

@section('content')
<div class="container-x py-10">
    <h1 class="text-3xl font-semibold tracking-tight">Your cart</h1>

    @if ($errors->has('cart'))<p class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-danger" role="alert">{{ $errors->first('cart') }}</p>@endif

    @if (empty($calc['lines']))
        <div class="mt-10 rounded-2xl border border-dashed border-line p-14 text-center">
            <p class="font-medium">Your cart is empty.</p>
            <a href="{{ route('shop') }}" class="btn btn-primary mt-6">Start shopping</a>
        </div>
    @else
    <div class="mt-8 grid gap-10 lg:grid-cols-[1fr_22rem]">
        <div>
            @foreach ($calc['errors'] as $e) <p class="mb-3 rounded-xl bg-amber-50 p-3 text-sm text-warn" role="alert">{{ $e }}</p> @endforeach
            <ul class="divide-y divide-line border-y border-line">
                @foreach ($calc['lines'] as $l)
                    @php $v = $l['variant']; @endphp
                    <li class="flex gap-4 py-5">
                        <a href="{{ route('product', $v->product) }}" class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-mist ring-1 ring-line">@if ($img = $v->imageUrl())<img src="{{ $img }}" alt="{{ $v->product->name }}" class="h-full w-full object-contain" loading="lazy">@else<span class="text-2xl font-semibold text-accent/60">{{ mb_substr($v->product->name, 0, 1) }}</span>@endif</a>
                        <div class="flex-1">
                            <a href="{{ route('product', $v->product) }}" class="font-medium">{{ $v->product->name }}</a>
                            @if ($v->name)<p class="text-sm text-ink-soft">{{ $v->name }}</p>@endif
                            <p class="text-sm text-ink-soft">SKU {{ $v->sku }}</p>
                            <p class="mt-1 text-sm">{{ Money::format($l['unit_price']) }}
                                @if ($l['price_source'] === 'tier' || $l['price_source'] === 'negotiated') <span class="ml-1 rounded-full bg-accent-soft px-2 py-0.5 text-xs text-accent">{{ $l['price_source'] === 'tier' ? 'Wholesale tier' : 'Negotiated' }}</span>@endif
                            </p>
                            <div class="mt-3 flex items-center gap-4">
                                <form method="post" action="{{ route('cart.update') }}" class="flex items-center gap-2">@csrf @method('PATCH')
                                    <input type="hidden" name="variant_id" value="{{ $v->id }}">
                                    <label class="sr-only" for="q{{ $v->id }}">Quantity</label>
                                    <input id="q{{ $v->id }}" type="number" name="quantity" value="{{ $l['quantity'] }}" min="0" class="field !w-20 !py-2">
                                    <button class="text-sm text-accent">Update</button>
                                </form>
                                <form method="post" action="{{ route('cart.remove', $v->id) }}">@csrf @method('DELETE')<button class="text-sm text-ink-soft underline">Remove</button></form>
                            </div>
                        </div>
                        <p class="font-semibold">{{ Money::format($l['line_total']) }}</p>
                    </li>
                @endforeach
            </ul>
        </div>

        <aside class="h-fit rounded-2xl bg-mist p-6">
            <h2 class="font-semibold">Order summary</h2>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ Money::format($calc['subtotal']) }}</dd></div>
                @if ((float) $calc['discount'] > 0)<div class="flex justify-between text-ok"><dt>Discount ({{ $calc['coupon'] }})</dt><dd>-{{ Money::format($calc['discount']) }}</dd></div>@endif
                <div class="flex justify-between text-ink-soft"><dt>Shipping</dt><dd>Calculated at checkout</dd></div>
                @if ((float) $calc['tax'] > 0)<div class="flex justify-between"><dt>Tax</dt><dd>{{ Money::format($calc['tax']) }}</dd></div>@endif
            </dl>
            <p class="mt-4 flex justify-between border-t border-line pt-4 font-semibold"><span>Total (before shipping)</span><span>{{ Money::format($calc['total']) }}</span></p>

            @if ($cart->coupon_code)
                <form method="post" action="{{ route('cart.coupon.remove') }}" class="mt-4 flex items-center justify-between text-sm">@csrf @method('DELETE')<span>Coupon <strong>{{ $cart->coupon_code }}</strong></span><button class="underline">Remove</button></form>
            @else
                <form method="post" action="{{ route('cart.coupon') }}" class="mt-4 flex gap-2">@csrf
                    <label class="sr-only" for="coupon">Coupon code</label>
                    <input id="coupon" name="coupon" class="field !py-2" placeholder="Coupon code">
                    <button class="btn btn-ghost !px-4">Apply</button>
                </form>
                @error('coupon')<p class="mt-2 text-xs text-danger" role="alert">{{ $message }}</p>@enderror
            @endif

            @if ($calc['errors'])
                <span class="btn btn-primary mt-6 w-full cursor-not-allowed opacity-40" aria-disabled="true">Resolve issues to checkout</span>
            @else
                <a href="{{ route('checkout.show') }}" class="btn btn-primary mt-6 w-full">Checkout</a>
            @endif
        </aside>
    </div>
    @endif
</div>
@endsection
