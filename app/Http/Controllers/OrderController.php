<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payments\BankTransferGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class OrderController extends Controller
{
    /** Confirmation / detail page for guests (signed URL or just-placed session) and owners. */
    public function show(Request $request, Order $order)
    {
        $this->authorizeAccess($request, $order);

        return view('orders.show', [
            'order' => $order->load('items', 'shipments', 'payments'),
            'bank' => $order->payment_method === 'bank_transfer' ? BankTransferGateway::instructions() : [],
        ])->with('layoutNoIndex', true);
    }

    public function trackForm()
    {
        return view('orders.track');
    }

    /** Guest order lookup: reference + email must both match; response is identical on failure. */
    public function track(Request $request)
    {
        $data = $request->validate(['reference' => 'required|string|max:30', 'email' => 'required|email']);
        $order = Order::where('reference', strtoupper(trim($data['reference'])))->where('email', $data['email'])->first();

        if (! $order) {
            return back()->withInput()->withErrors(['reference' => 'We could not find an order matching those details.']);
        }

        return redirect(URL::temporarySignedRoute('order.show', now()->addMinutes(30), ['order' => $order->reference]));
    }

    public function invoice(Request $request, Order $order)
    {
        $this->authorizeAccess($request, $order);
        abort_if(in_array($order->status, ['pending', 'cancelled'], true) && $order->payment_status === 'cancelled', 404);

        return view('orders.invoice', ['order' => $order->load('items')]);
    }

    private function authorizeAccess(Request $request, Order $order): void
    {
        $user = $request->user();
        $ok = ($user && $order->user_id === $user->id)
            || $request->hasValidSignature()
            || ($order->guest_token && $request->session()->get('order_access.'.$order->reference));

        abort_unless($ok, 404); // 404 rather than 403: do not confirm the order exists
    }
}
