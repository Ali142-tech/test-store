<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\StoreSetting;

/** Cash on delivery: payment stays pending until the courier collects cash and an admin (or delivery) confirms it. */
class CodGateway implements PaymentGateway
{
    public function code(): string
    {
        return 'cod';
    }

    public function label(): string
    {
        return 'Cash on delivery';
    }

    public function description(): string
    {
        return 'Pay in cash when your order arrives.';
    }

    public function isEnabled(string $audience): bool
    {
        if (! StoreSetting::get('payment_cod_enabled', '1')) {
            return false;
        }

        return $audience === 'retail' || (bool) StoreSetting::get('payment_cod_wholesale', '0');
    }

    public function initiate(Order $order, Payment $payment): void
    {
        // Nothing to collect online; order.payment_status stays 'unpaid' until delivery confirmation.
    }
}
