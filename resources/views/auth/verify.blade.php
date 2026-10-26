@extends('layouts.app', ['metaTitle' => 'Verify your email'])
@section('content')
<x-auth.card title="Verify your email" subtitle="We sent a verification link to your inbox. Click it to activate your account.">
    <form method="post" action="{{ route('verification.send') }}">@csrf<button class="btn btn-ghost">Resend verification email</button></form>
</x-auth.card>
@endsection
