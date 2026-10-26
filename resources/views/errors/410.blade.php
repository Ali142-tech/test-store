@extends('layouts.app', ['metaTitle' => 'No longer available'])

@section('content')
<div class="container-x py-28 text-center">
    <p class="eyebrow">Discontinued</p>
    <h1 class="mt-3 text-4xl font-semibold tracking-tight">This product is no longer available.</h1>
    <p class="mt-3 text-ink-soft">Browse similar accessories instead.</p>
    <a href="{{ route('shop') }}" class="btn btn-primary mt-8">Continue shopping</a>
</div>
@endsection
