@extends('layouts.app', ['metaTitle' => 'Reset password'])
@section('content')
<x-auth.card title="Reset your password" subtitle="We will email you a reset link.">
    <form method="post" action="{{ route('password.email') }}" class="space-y-4">@csrf
        <x-field name="email" label="Email" type="email" required />
        <button class="btn btn-primary w-full">Send reset link</button>
    </form>
</x-auth.card>
@endsection
