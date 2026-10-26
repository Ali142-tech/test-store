@extends('layouts.app', ['metaTitle' => 'Contact us'])
@section('content')
<div class="container-x max-w-2xl py-14">
    <h1 class="text-3xl font-semibold tracking-tight">Contact us</h1>
    <p class="mt-2 text-ink-soft">Questions about an order, a product, or wholesale pricing? Send us a message.
        @if ($e = \App\Models\StoreSetting::get('contact_email')) You can also email {{ $e }}.@endif</p>
    <form method="post" action="{{ route('contact.store') }}" class="mt-8 space-y-4">@csrf
        <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="grid gap-4 sm:grid-cols-2"><x-field name="name" label="Name" required /><x-field name="email" label="Email" type="email" required /></div>
        <div class="grid gap-4 sm:grid-cols-2"><x-field name="phone" label="Phone" /><x-field name="subject" label="Subject" /></div>
        <div><label for="message" class="text-sm font-medium">Message <span class="text-danger">*</span></label>
            <textarea id="message" name="message" rows="5" required class="field mt-1.5">{{ old('message') }}</textarea>
            @error('message')<p class="mt-1 text-xs text-danger" role="alert">{{ $message }}</p>@enderror</div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_wholesale_lead" value="1"> I am a business interested in wholesale</label>
        <button class="btn btn-primary">Send message</button>
    </form>
</div>
@endsection
