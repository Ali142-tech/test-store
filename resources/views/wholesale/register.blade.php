@extends('layouts.app', ['metaTitle' => 'Wholesale account'])
@section('content')
<div class="container-x max-w-2xl py-14">
    <p class="eyebrow">For shops and resellers</p>
    <h1 class="mt-2 text-3xl font-semibold tracking-tight">Apply for a wholesale account</h1>
    <p class="mt-2 text-ink-soft">We review each application. Once approved you will see tiered bulk pricing, can place bulk orders and request custom quotes.</p>
    <form method="post" action="{{ route('wholesale.register.store') }}" enctype="multipart/form-data" class="mt-8 space-y-4">@csrf
        @guest
            <h2 class="font-semibold">Your login</h2>
            <div class="grid gap-4 sm:grid-cols-2"><x-field name="name" label="Your name" required /><x-field name="email" label="Email" type="email" required /></div>
            <div class="grid gap-4 sm:grid-cols-2"><x-field name="password" label="Password" type="password" required /><x-field name="password_confirmation" label="Confirm password" type="password" required /></div>
        @endguest
        <h2 class="pt-4 font-semibold">Business details</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="business_name" label="Business name" required /><x-field name="business_type" label="Business type" hint="e.g. mobile shop, distributor" />
            <x-field name="contact_name" label="Contact person" required /><x-field name="contact_phone" label="Contact phone" type="tel" required />
            <x-field name="tax_number" label="NTN / registration no. (optional)" /><x-field name="city" label="City" required />
        </div>
        <x-field name="business_address" label="Business address" required />
        <div><label for="document" class="text-sm font-medium">Verification document (optional)</label>
            <input id="document" name="document" type="file" accept=".pdf,.jpg,.jpeg,.png" class="field mt-1.5">
            <p class="mt-1 text-xs text-ink-soft">PDF, JPG or PNG, max 4 MB. Stored privately; only reviewers can open it.</p>
            @error('document')<p class="mt-1 text-xs text-danger" role="alert">{{ $message }}</p>@enderror</div>
        <x-field name="notes" label="Anything else we should know? (optional)" />
        <button class="btn btn-primary">Submit application</button>
    </form>
</div>
@endsection
