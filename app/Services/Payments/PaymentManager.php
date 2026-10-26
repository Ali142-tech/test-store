<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Collection;

/**
 * Registry of payment gateways. Online gateways (JazzCash, Easypaisa, card) are NOT registered here:
 * they require merchant credentials and provider documentation. Add an adapter class implementing
 * PaymentGateway and list it in $gateways once real credentials exist (see docs/PAYMENTS.md).
 */
class PaymentManager
{
    /** @var array<string, PaymentGateway> */
    private array $gateways = [];

    public function __construct()
    {
        foreach ([new CodGateway, new BankTransferGateway] as $g) {
            $this->gateways[$g->code()] = $g;
        }
    }

    public function register(PaymentGateway $gateway): void
    {
        $this->gateways[$gateway->code()] = $gateway;
    }

    /** @return Collection<string, PaymentGateway> */
    public function available(string $audience): Collection
    {
        return collect($this->gateways)->filter(fn (PaymentGateway $g) => $g->isEnabled($audience));
    }

    public function isEnabled(string $code, string $audience): bool
    {
        return $this->available($audience)->has($code);
    }

    public function initiate(Order $order, string $code): Payment
    {
        $payment = $order->payments()->create([
            'method' => $code, 'status' => 'pending', 'amount' => $order->grand_total, 'currency' => $order->currency,
        ]);
        $this->gateways[$code]->initiate($order, $payment);

        return $payment;
    }
}
