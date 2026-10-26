@extends('layouts.app', ['metaTitle' => $page->seo_title ?: $page->title, 'metaDescription' => $page->seo_description])

@section('content')
<article class="container-x max-w-3xl py-14">
    <h1 class="text-3xl font-semibold tracking-tight">{{ $page->title }}</h1>
    {{-- Admin-authored HTML, sanitised at render by Page::safeBody(). --}}
    <div class="mt-6 space-y-4 text-ink-soft">{!! $page->safeBody() !!}</div>
</article>
@endsection
