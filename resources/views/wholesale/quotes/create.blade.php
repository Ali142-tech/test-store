@extends('layouts.app', ['metaTitle' => 'Request a quote'])
@section('content')
<div class="container-x max-w-3xl py-12" x-data="{ rows: [{ sku: '', quantity: 100, target_price: '' }] }">
    <h1 class="text-3xl font-semibold tracking-tight">Request a quote</h1>
    <p class="mt-2 text-ink-soft">List the products, quantities and your target prices. Our team will reply with a formal quote.</p>
    @error('items')<p class="mt-4 rounded-xl bg-red-50 dark:bg-red-950/40 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
    <form method="post" action="{{ route('quotes.store') }}" class="mt-8 space-y-3">@csrf
        <template x-for="(r, i) in rows" :key="i">
            <div class="grid grid-cols-[1fr_6rem_8rem_auto] gap-2">
                <input :name="`items[${i}][sku]`" x-model="r.sku" class="field" placeholder="SKU" required aria-label="SKU">
                <input :name="`items[${i}][quantity]`" x-model="r.quantity" type="number" min="1" class="field" required aria-label="Quantity">
                <input :name="`items[${i}][target_price]`" x-model="r.target_price" type="number" min="0" step="0.01" class="field" placeholder="Target price" aria-label="Target price">
                <button type="button" class="px-2 text-ink-soft" @click="rows.splice(i, 1)" x-show="rows.length > 1" aria-label="Remove line">✕</button>
            </div>
        </template>
        <button type="button" class="text-sm text-accent" @click="rows.push({ sku: '', quantity: 100, target_price: '' })">+ Add another product</button>
        <div><label for="notes" class="text-sm font-medium">Notes</label><textarea id="notes" name="notes" rows="3" class="field mt-1.5">{{ old('notes') }}</textarea></div>
        <button class="btn btn-primary">Submit request</button>
    </form>
</div>
@endsection
