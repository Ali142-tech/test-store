<x-mail::message>
# {{ $application->status === 'approved' ? 'Welcome to wholesale' : 'About your application' }}

@if ($application->status === 'approved')
Your wholesale account for **{{ $application->business_name }}** has been approved. You can now see tiered pricing, place bulk orders and request quotes.

<x-mail::button :url="route('wholesale.dashboard')">Open wholesale dashboard</x-mail::button>
@else
We were unable to approve the wholesale application for **{{ $application->business_name }}** at this time.
@if ($application->admin_notes)

Reason: {{ $application->admin_notes }}
@endif

You are welcome to contact us with more information.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
