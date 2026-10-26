@extends('layouts.app', ['metaTitle' => 'Track your order'])
@section('content')
<x-auth.card title="Track your order" subtitle="Enter the order reference from your confirmation email and the email used at checkout.">
    <form method="post" action="{{ route('order.track.lookup') }}" class="space-y-4">@csrf
        <x-field name="reference" label="Order reference" required placeholder="ORD-250101-ABCDE" />
        <x-field name="email" label="Email" type="email" required />
        <button class="btn btn-primary w-full">Find my order</button>
    </form>
</x-auth.card>
@endsection
