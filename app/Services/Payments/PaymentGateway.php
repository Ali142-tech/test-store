<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;

interface PaymentGateway
{
    public function code(): string;

    public function label(): string;

    public function description(): string;

    /** Enabled by the admin AND (for online gateways) configured with real credentials. */
    public function isEnabled(string $audience): bool;

    /** Prepare the payment record for a freshly placed order. */
    public function initiate(Order $order, Payment $payment): void;
}
