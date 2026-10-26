@extends('layouts.app', ['metaTitle' => 'Page not found'])

@section('content')
<div class="container-x py-28 text-center">
    <p class="eyebrow">404</p>
    <h1 class="mt-3 text-4xl font-semibold tracking-tight">We couldn't find that page.</h1>
    <p class="mt-3 text-ink-soft">It may have moved or no longer exists.</p>
    <a href="{{ route('shop') }}" class="btn btn-primary mt-8">Continue shopping</a>
</div>
@endsection
