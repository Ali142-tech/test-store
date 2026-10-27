<x-mail::message>
# {{ $kind === 'placed' ? 'Thank you for your order' : 'Your order has been updated' }}

Order **{{ $order->reference }}** - status: **{{ ucfirst($order->status) }}**.

<x-mail::table>
| Item | Qty | Total |
|:-----|:---:|------:|
@foreach ($order->items as $i)
| {{ $i->product_name }}{{ $i->variant_name ? ' ('.$i->variant_name.')' : '' }} | {{ $i->quantity }} | {{ \App\Support\Money::order($i->line_total, $order) }} |
@endforeach
</x-mail::table>

Subtotal: {{ \App\Support\Money::order($order->subtotal, $order) }}  
Discount: -{{ \App\Support\Money::order($order->discount_total, $order) }}  
Shipping: {{ \App\Support\Money::order($order->shipping_total, $order) }}  
**Total: {{ \App\Support\Money::order($order->grand_total, $order) }}**

@if ($order->payment_method === 'bank_transfer' && $order->payment_status === 'pending')
Please transfer the total using **{{ $order->reference }}** as the payment reference:

@foreach (\App\Services\Payments\BankTransferGateway::instructions() as $label => $value)
- {{ $label }}: {{ $value }}
@endforeach
@elseif ($order->payment_method === 'cod')
Payment: cash on delivery.
@endif

@if ($order->guest_token)
<x-mail::button :url="\Illuminate\Support\Facades\URL::signedRoute('order.show', ['order' => $order->reference])">View your order</x-mail::button>
@else
<x-mail::button :url="route('account.orders.show', $order->reference)">View your order</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
