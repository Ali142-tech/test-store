@php
    use App\Support\Money;
    $u = auth()->user();
@endphp
@extends('layouts.app', ['metaTitle' => 'Checkout'])

@section('content')
<div class="container-x py-10" x-data="checkout()" x-init="refresh()">
    <h1 class="text-3xl font-semibold tracking-tight">Checkout</h1>
    @if ($quote)<p class="mt-2 text-sm text-ink-soft">Quote {{ $quote->reference }}: agreed prices are applied.</p>@endif

    @if ($errors->has('checkout'))
        <div class="mt-4 rounded-xl bg-red-50 dark:bg-red-950/40 p-4 text-sm text-danger" role="alert">
            @foreach ($errors->get('checkout') as $group) @foreach ((array) $group as $m)<p>{{ $m }}</p>@endforeach @endforeach
        </div>
    @endif

    <form method="post" action="{{ $action }}" class="mt-8 grid gap-10 lg:grid-cols-[minmax(0,1fr)_24rem]" @submit="submitting = true">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        <div class="min-w-0 space-y-10">
            <section>
                <h2 class="text-lg font-semibold">1. Contact</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-field name="email" label="Email" type="email" :value="$u?->email" required />
                    <x-field name="phone" label="Phone" type="tel" :value="$u?->phone" required />
                </div>
                @guest <p class="mt-2 text-sm text-ink-soft">Checking out as a guest. <a class="text-accent" href="{{ route('login') }}">Sign in</a> for faster checkout.</p> @endguest
            </section>

            <section>
                <h2 class="text-lg font-semibold">2. Shipping address</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-field name="shipping[full_name]" label="Full name" :value="$default?->full_name ?? $u?->name" required />
                    <x-field name="shipping[phone]" label="Phone" type="tel" :value="$default?->phone ?? $u?->phone" required />
                    <div class="sm:col-span-2"><x-field name="shipping[line1]" label="Address" :value="$default?->line1" required /></div>
                    <div class="sm:col-span-2"><x-field name="shipping[line2]" label="Apartment, suite (optional)" :value="$default?->line2" /></div>
                    <x-field name="shipping[city]" label="City" :value="$default?->city" required x-model="city" @change.debounce.400ms="refresh()" />
                    <x-field name="shipping[state]" label="Province / state" :value="$default?->state" />
                    <x-field name="shipping[postal_code]" label="Postal code" :value="$default?->postal_code" />
                    <input type="hidden" name="shipping[country]" value="PK">
                </div>
            </section>

            <section>
                <h2 class="text-lg font-semibold">3. Billing address</h2>
                <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="billing_same" value="1" x-model="billingSame" checked> Same as shipping address</label>
                <div x-show="!billingSame" x-cloak class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-field name="billing[full_name]" label="Full name" />
                    <x-field name="billing[phone]" label="Phone" type="tel" />
                    <div class="sm:col-span-2"><x-field name="billing[line1]" label="Address" /></div>
                    <x-field name="billing[city]" label="City" />
                    <x-field name="billing[postal_code]" label="Postal code" />
                </div>
            </section>

            <section>
                <h2 class="text-lg font-semibold">4. Shipping method</h2>
                <p x-show="!city" class="mt-3 text-sm text-ink-soft">Enter your city to see delivery options.</p>
                <p x-show="city && options.length === 0 && !loading" x-cloak class="mt-3 text-sm text-danger">We do not deliver to this city yet.</p>
                <div class="mt-3 space-y-2">
                    <template x-for="o in options" :key="o.id">
                        <label class="flex cursor-pointer items-center justify-between rounded-xl border border-line p-4" :class="method == o.id ? 'border-accent bg-accent-soft/40' : ''">
                            <span class="flex items-center gap-3"><input type="radio" name="shipping_method_id" :value="o.id" x-model="method" @change="refresh()" required>
                                <span><span x-text="o.name" class="font-medium"></span><span x-show="o.eta" class="block text-xs text-ink-soft" x-text="o.eta"></span></span></span>
                            <span x-text="Number(o.cost) === 0 ? 'Free' : money(o.cost)"></span>
                        </label>
                    </template>
                </div>
                @error('shipping_method_id')<p class="mt-1 text-xs text-danger" role="alert">{{ $message }}</p>@enderror
            </section>

            <section>
                <h2 class="text-lg font-semibold">5. Payment</h2>
                <div class="mt-3 space-y-2">
                    @forelse ($methods as $code => $g)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line p-4">
                            <input type="radio" name="payment_method" value="{{ $code }}" class="mt-1" @checked(old('payment_method', $loop->first ? $code : null) === $code) required>
                            <span><span class="font-medium">{{ $g->label() }}</span><span class="block text-sm text-ink-soft">{{ $g->description() }}</span>@if ($code === 'bank_transfer')<span class="mt-2 block rounded-xl bg-mist p-3 text-sm"><span class="block font-medium">Pay into this account, then we confirm your order:</span>@foreach (\App\Services\Payments\BankTransferGateway::instructions() as $label => $value)<span class="block break-words text-ink-soft">{{ $label }}: <span class="break-all text-ink">{{ $value }}</span></span>@endforeach<span class="mt-1 block text-xs text-ink-soft">You will get your order reference after placing the order; use it as the payment reference. No card details are needed.</span></span>@endif</span>
                        </label>
                    @empty
                        <p class="text-sm text-danger">No payment methods are currently available. Please contact us.</p>
                    @endforelse
                </div>
                @error('payment_method')<p class="mt-1 text-xs text-danger" role="alert">{{ $message }}</p>@enderror
            </section>

            <section>
                <x-field name="note" label="Order note (optional)" />
                <label class="mt-4 flex items-start gap-2 text-sm"><input type="checkbox" name="terms" value="1" required class="mt-1"> I agree to the <a class="text-accent underline" target="_blank" href="{{ route('page', 'terms-and-conditions') }}">terms</a> and <a class="text-accent underline" target="_blank" href="{{ route('page', 'returns-and-refunds') }}">returns policy</a>.</label>
                @error('terms')<p class="mt-1 text-xs text-danger" role="alert">{{ $message }}</p>@enderror
            </section>
        </div>

        <aside class="h-fit rounded-2xl bg-mist p-6 lg:sticky lg:top-24">
            <h2 class="font-semibold">6. Review order</h2>
            <ul class="mt-4 space-y-3 text-sm">
                @foreach ($calc['lines'] as $l)
                    <li class="flex items-center justify-between gap-3"><span class="flex items-center gap-3">@if ($img = $l['variant']->imageUrl())<img src="{{ $img }}" alt="" class="photo-bg h-12 w-12 shrink-0 rounded-lg object-contain ring-1 ring-line">@endif<span class="min-w-0 break-words">{{ $l['quantity'] }} × {{ $l['variant']->displayName() }}</span></span><span>{{ Money::format($l['line_total']) }}</span></li>
                @endforeach
            </ul>
            <template x-for="e in errors"><p class="mt-3 text-sm text-danger" x-text="e" role="alert"></p></template>
            <dl class="mt-4 space-y-2 border-t border-line pt-4 text-sm">
                <div class="flex justify-between"><dt>Subtotal</dt><dd x-text="money(t.subtotal)"></dd></div>
                <div class="flex justify-between text-ok" x-show="Number(t.discount) > 0"><dt>Discount</dt><dd x-text="'-' + money(t.discount)"></dd></div>
                <div class="flex justify-between"><dt>Shipping</dt><dd x-text="method ? (Number(t.shipping) === 0 ? 'Free' : money(t.shipping)) : '-'"></dd></div>
                <div class="flex justify-between" x-show="Number(t.tax) > 0"><dt>Tax</dt><dd x-text="money(t.tax)"></dd></div>
            </dl>
            <p class="mt-4 flex justify-between border-t border-line pt-4 text-lg font-semibold"><span>Total</span><span x-text="money(t.total)"></span></p>
            <button class="btn btn-primary mt-6 w-full disabled:opacity-50" :disabled="submitting || !method">
                <span x-show="!submitting">Place order</span><span x-show="submitting" x-cloak>Placing order…</span>
            </button>
            <p class="mt-3 text-xs text-ink-soft">Final prices and stock are confirmed on our server when you place the order.</p>
        </aside>
    </form>
</div>

<script>
function checkout() {
    return {
        city: @js(old('shipping.city', $default?->city ?? '')), method: @js(old('shipping_method_id')), options: [], errors: [], loading: false, submitting: false, billingSame: true,
        t: { subtotal: @js($calc['subtotal']), discount: @js($calc['discount']), shipping: '0', tax: @js($calc['tax']), total: @js($calc['total']) },
        money(n) { return @js(\App\Models\StoreSetting::get('currency_symbol', 'Rs')) + ' ' + Number(n).toLocaleString('en-US', { maximumFractionDigits: 0 }); },
        async refresh() {
            if (!this.city) return;
            this.loading = true;
            const p = new URLSearchParams({ city: this.city }); if (this.method) p.set('shipping_method_id', this.method);
            const r = await fetch(@js($summaryUrl) + '?' + p, { headers: { Accept: 'application/json' } });
            if (r.ok) { const d = await r.json(); this.options = d.shipping_options; this.errors = d.errors; this.t = d;
                if (this.method && !this.options.find(o => o.id == this.method)) this.method = null; }
            this.loading = false;
        },
    };
}
</script>
@endsection
