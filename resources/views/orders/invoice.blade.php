@php use App\Support\Money; $s = fn ($k) => \App\Models\StoreSetting::get($k); @endphp
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>Invoice {{ $order->reference }}</title>
<style>body{font-family:system-ui,sans-serif;max-width:760px;margin:2rem auto;padding:0 1rem;color:#0f1115}table{width:100%;border-collapse:collapse}th,td{padding:.5rem;border-bottom:1px solid #e6e8ec;text-align:left}td.r,th.r{text-align:right}@media print{button{display:none}}</style></head>
<body>
<button onclick="window.print()">Print / save as PDF</button>
<h1>{{ $s('store_name') }} - Invoice</h1>
<p>Invoice for order <strong>{{ $order->reference }}</strong><br>Date: {{ $order->placed_at?->format('d M Y') }}<br>Payment: {{ str_replace('_', ' ', $order->payment_status) }}</p>
<p><strong>Bill to</strong><br>{{ $order->billing_address['full_name'] }}<br>{{ $order->billing_address['line1'] }}, {{ $order->billing_address['city'] }}<br>{{ $order->email }}</p>
<table><thead><tr><th>Item</th><th>SKU</th><th class="r">Qty</th><th class="r">Unit</th><th class="r">Total</th></tr></thead><tbody>
@foreach ($order->items as $i)<tr><td>{{ $i->product_name }} {{ $i->variant_name }}</td><td>{{ $i->sku }}</td><td class="r">{{ $i->quantity }}</td><td class="r">{{ Money::order($i->unit_price, $order) }}</td><td class="r">{{ Money::order($i->line_total, $order) }}</td></tr>@endforeach
</tbody></table>
<p style="text-align:right">Subtotal {{ Money::order($order->subtotal, $order) }}<br>Discount -{{ Money::order($order->discount_total, $order) }}<br>Shipping {{ Money::order($order->shipping_total, $order) }}<br>Tax {{ Money::order($order->tax_total, $order) }}<br><strong>Total {{ Money::order($order->grand_total, $order) }}</strong></p>
</body></html>
