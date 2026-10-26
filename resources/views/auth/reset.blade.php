@extends('layouts.app', ['metaTitle' => 'Choose a new password'])
@section('content')
<x-auth.card title="Choose a new password">
    <form method="post" action="{{ route('password.update') }}" class="space-y-4">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field name="email" label="Email" type="email" :value="$email" required />
        <x-field name="password" label="New password" type="password" required />
        <x-field name="password_confirmation" label="Confirm password" type="password" required />
        <button class="btn btn-primary w-full">Update password</button>
    </form>
</x-auth.card>
@endsection
