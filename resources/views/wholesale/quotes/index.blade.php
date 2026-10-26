@extends('layouts.app', ['metaTitle' => 'My quotes'])
@section('content')
<div class="container-x max-w-3xl py-12">
    <div class="flex items-center justify-between"><h1 class="text-2xl font-semibold">My quotes</h1><a class="btn btn-primary" href="{{ route('quotes.create') }}">Request a quote</a></div>
    @forelse ($quotes as $q)
        <a href="{{ route('quotes.show', $q->reference) }}" class="mt-3 flex justify-between rounded-xl border border-line p-4 text-sm hover:border-accent"><span>{{ $q->reference }}<span class="block text-xs text-ink-soft">{{ $q->created_at->format('d M Y') }}</span></span><span class="chip">{{ ucfirst($q->status) }}</span></a>
    @empty <p class="mt-6 text-sm text-ink-soft">No quotes yet.</p> @endforelse
    <div class="mt-6">{{ $quotes->links() }}</div>
</div>
@endsection
