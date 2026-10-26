@extends('account.layout', ['metaTitle' => 'Wishlist'])
@section('account')
<h1 class="text-2xl font-semibold">Wishlist</h1>
@if ($products->isEmpty())
    <p class="mt-4 text-sm text-ink-soft">Nothing saved yet. Use "Add to wishlist" on a product page.</p>
@else
    <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-3">@foreach ($products as $p)<x-product-card :product="$p" />@endforeach</div>
@endif
@endsection
