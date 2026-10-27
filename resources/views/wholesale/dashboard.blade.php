@php use App\Support\Money; @endphp
@extends('layouts.app', ['metaTitle' => 'Wholesale dashboard'])
@section('content')
<div class="container-x max-w-4xl py-12">
    <h1 class="text-2xl font-semibold">Wholesale · {{ $application->business_name }}</h1>
    <p class="mt-3">Application status:
        <span class="chip {{ $application->status === 'approved' ? '!border-ok text-ok' : ($application->status === 'rejected' ? '!border-danger text-danger' : '') }}">{{ ucfirst($application->status) }}</span></p>
    @if ($application->status === 'pending')<p class="mt-2 text-sm text-ink-soft">We are reviewing your application. You will be emailed once it is approved.</p>@endif
    @if ($application->status === 'rejected')<p class="mt-2 text-sm text-ink-soft">Your application was not approved. @if ($application->admin_notes) Reason: {{ $application->admin_notes }} @endif Please <a class="text-accent" href="{{ route('contact') }}">contact us</a>.</p>@endif

    @if ($approved)
        <div class="mt-8 grid gap-4 sm:grid-cols-3 text-sm">
            <a href="{{ route('wholesale.bulk') }}" class="rounded-2xl border border-line p-5 hover:border-accent"><p class="font-semibold">Bulk order</p><p class="text-ink-soft">Quick order by SKU or CSV</p></a>
            <a href="{{ route('quotes.create') }}" class="rounded-2xl border border-line p-5 hover:border-accent"><p class="font-semibold">Request a quote</p><p class="text-ink-soft">Negotiate prices</p></a>
            <a href="{{ route('quotes.index') }}" class="rounded-2xl border border-line p-5 hover:border-accent"><p class="font-semibold">My quotes</p><p class="text-ink-soft">{{ $quotes->count() }} recent</p></a>
        </div>
        <h2 class="mt-10 font-semibold">Wholesale orders</h2>
        @forelse ($orders as $o)
            <a href="{{ route('account.orders.show', $o->reference) }}" class="mt-3 flex justify-between rounded-xl border border-line p-4 text-sm"><span>{{ $o->reference }} · {{ ucfirst($o->status) }}</span><span>{{ Money::order($o->grand_total, $o) }}</span></a>
        @empty <p class="mt-3 text-sm text-ink-soft">No wholesale orders yet.</p> @endforelse
    @endif
</div>
@endsection
