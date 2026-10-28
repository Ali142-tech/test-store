@extends('account.layout', ['metaTitle' => 'Profile'])
@section('account')
<h1 class="text-2xl font-semibold">Profile</h1>
<form method="post" action="{{ route('account.profile.update') }}" class="mt-6 grid max-w-xl gap-4">@csrf @method('PUT')
    <x-field name="name" label="Name" :value="auth()->user()->name" required />
    <x-field name="email" label="Email" type="email" :value="auth()->user()->email" required />
    <x-field name="phone" label="Phone" :value="auth()->user()->phone" />
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="marketing_consent" value="1" @checked(auth()->user()->marketing_consent)> Send me offers and news</label>
    <button class="btn btn-primary w-fit">Save</button>
</form>

<h2 class="mt-12 text-xl font-semibold">Saved addresses</h2>
@foreach ($addresses as $a)
    <div class="mt-3 flex items-start justify-between rounded-xl border border-line p-4 text-sm">
        <p>{{ $a->full_name }}, {{ $a->line1 }}, {{ $a->city }} · {{ $a->phone }}</p>
        <form method="post" action="{{ route('account.addresses.destroy', $a->id) }}">@csrf @method('DELETE')<button class="text-danger underline">Remove</button></form>
    </div>
@endforeach
<form method="post" action="{{ route('account.addresses.store') }}" class="mt-6 grid max-w-xl gap-3 sm:grid-cols-2">@csrf
    <x-field name="full_name" label="Full name" required /><x-field name="phone" label="Phone" required />
    <div class="sm:col-span-2"><x-field name="line1" label="Address" required /></div>
    <x-field name="city" label="City" required /><x-field name="postal_code" label="Postal code" />
    <button class="btn btn-ghost sm:col-span-2">Add address</button>
</form>
@endsection
