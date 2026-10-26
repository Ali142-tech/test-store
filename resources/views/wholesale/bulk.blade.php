@extends('layouts.app', ['metaTitle' => 'Bulk order'])
@section('content')
<div class="container-x max-w-3xl py-12" x-data="{ sku: '', qty: 10, res: null, err: null,
    async calc() { this.err = null; this.res = null; const r = await fetch('{{ route('wholesale.bulk.price') }}?' + new URLSearchParams({ sku: this.sku, quantity: this.qty }), { headers: { Accept: 'application/json' } });
        if (r.ok) this.res = await r.json(); else this.err = 'SKU not found or quantity invalid.'; } }">
    <h1 class="text-3xl font-semibold tracking-tight">Bulk order</h1>
    <p class="mt-2 text-ink-soft">Add many products at once. Wholesale prices and minimum quantities are applied by our server.</p>

    @if ($errors->has('bulk'))<ul class="mt-4 list-disc space-y-1 rounded-xl bg-amber-50 p-4 pl-8 text-sm text-warn" role="alert">@foreach ($errors->get('bulk') as $g)@foreach ((array) $g as $m)<li>{{ $m }}</li>@endforeach @endforeach</ul>@endif
    @error('lines')<p class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror

    <form method="post" action="{{ route('wholesale.bulk.add') }}" enctype="multipart/form-data" class="mt-8 space-y-4">@csrf
        <label for="lines" class="text-sm font-medium">Quick order: one "SKU, quantity" per line</label>
        <textarea id="lines" name="lines" rows="8" class="field font-mono" placeholder="ARMOR-SHIELD-CASE-BLACK, 50&#10;GAN-65W-FAST-CHARGER-65W, 20">{{ old('lines') }}</textarea>
        <div><label for="csv" class="text-sm font-medium">…or upload a CSV</label> <a class="ml-2 text-sm text-accent underline" href="{{ route('wholesale.bulk.template') }}">Download template</a>
            <input id="csv" type="file" name="csv" accept=".csv,.txt" class="field mt-1.5"></div>
        <button class="btn btn-primary">Add to cart</button>
    </form>

    <section class="mt-12 rounded-2xl bg-mist p-6">
        <h2 class="font-semibold">Price calculator</h2>
        <div class="mt-3 flex flex-wrap gap-2">
            <input x-model="sku" class="field !w-64" placeholder="SKU" aria-label="SKU"><input x-model.number="qty" type="number" min="1" class="field !w-28" aria-label="Quantity">
            <button type="button" class="btn btn-ghost" @click="calc()">Calculate</button>
        </div>
        <p x-show="err" x-text="err" class="mt-3 text-sm text-danger"></p>
        <div x-show="res" x-cloak class="mt-3 text-sm"><p x-text="res?.name" class="font-medium"></p>
            <p>Unit price: <strong x-text="Number(res?.unit_price).toLocaleString()"></strong> (<span x-text="res?.source"></span>) · Total: <strong x-text="Number(res?.line_total).toLocaleString()"></strong></p>
            <p class="text-ink-soft">Minimum quantity <span x-text="res?.moq"></span> · in stock <span x-text="res?.available"></span></p></div>
    </section>
</div>
@endsection
