<x-mail::message>
# Your quote is ready

Quote **{{ $quote->reference }}** total: **{{ number_format((float) $quote->quoted_total + (float) ($quote->shipping_amount ?? 0)) }}** @if ($quote->shipping_amount !== null)(shipping {{ number_format((float) $quote->shipping_amount) }} included)@else(shipping calculated at checkout)@endif.
@if ($quote->expires_at)

Valid until **{{ $quote->expires_at->format('d M Y') }}**.
@endif
@if ($quote->admin_notes)

{{ $quote->admin_notes }}
@endif

<x-mail::button :url="route('quotes.show', $quote->reference)">Review and accept</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
