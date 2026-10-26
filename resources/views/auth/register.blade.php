@extends('layouts.app', ['metaTitle' => 'Create account'])
@section('content')
<x-auth.card title="Create your account">
    <form method="post" action="{{ route('register.store') }}" class="space-y-4">
        @csrf
        <x-field name="name" label="Full name" required autocomplete="name" />
        <x-field name="email" label="Email" type="email" required autocomplete="email" />
        <x-field name="phone" label="Phone" type="tel" autocomplete="tel" />
        <x-field name="password" label="Password" type="password" required hint="At least 8 characters." autocomplete="new-password" />
        <x-field name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="marketing_consent" value="1" class="mt-1"> Email me offers and news (optional, unsubscribe anytime).</label>
        <button class="btn btn-primary w-full">Create account</button>
    </form>
    <p class="mt-6 text-sm text-ink-soft">Have an account? <a class="text-accent" href="{{ route('login') }}">Sign in</a></p>
</x-auth.card>
@endsection
