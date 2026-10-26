@extends('layouts.app', ['metaTitle' => 'FAQs'])

@section('content')
<div class="container-x max-w-3xl py-14">
    <h1 class="text-3xl font-semibold tracking-tight">Frequently asked questions</h1>
    <div class="mt-8 divide-y divide-line border-y border-line">
        @forelse ($faqs as $f)
            <details class="py-4"><summary class="cursor-pointer font-medium">{{ $f->question }}</summary><p class="mt-3 text-ink-soft">{{ $f->answer }}</p></details>
        @empty
            <p class="py-6 text-ink-soft">No FAQs yet.</p>
        @endforelse
    </div>
</div>
@endsection
