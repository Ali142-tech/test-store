@extends('layouts.app', ['metaTitle' => 'Sign in'])
@section('content')
<x-auth.card title="Sign in" subtitle="Welcome back.">
    <form method="post" action="{{ route('login.store') }}" class="space-y-4">
        @csrf
        <x-field name="email" label="Email" type="email" required autocomplete="email" />
        <x-field name="password" label="Password" type="password" required autocomplete="current-password" />
        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="remember" value="1"> Remember me</label>
            <a href="{{ route('password.request') }}" class="text-accent">Forgot password?</a>
        </div>
        <button class="btn btn-primary w-full">Sign in</button>
    </form>
    <p class="mt-6 text-sm text-ink-soft">New here? <a class="text-accent" href="{{ route('register') }}">Create an account</a> · <a class="text-accent" href="{{ route('wholesale.register') }}">Wholesale account</a></p>
</x-auth.card>
@endsection
