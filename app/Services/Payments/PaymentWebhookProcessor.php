<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Services\Orders\OrderService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Provider-agnostic, idempotent handling of an ALREADY SIGNATURE-VERIFIED payment notification.
 * A gateway adapter must verify the provider signature before calling this.
 */
class PaymentWebhookProcessor
{
    public function __construct(private OrderService $orders) {}

    /** @return string  processed|duplicate|rejected */
    public function process(string $provider, string $eventId, string $orderReference, string $amount, string $currency, bool $success, array $payload = []): string
    {
        return DB::transaction(function () use ($provider, $eventId, $orderReference, $amount, $currency, $success, $payload) {
            $payment = Payment::whereHas('order', fn ($q) => $q->where('reference', $orderReference))
                ->where('method', $provider)->lockForUpdate()->first();

            if (! $payment) {
                return 'rejected';
            }

            try {
                $tx = PaymentTransaction::create([
                    'payment_id' => $payment->id, 'provider' => $provider, 'event_id' => $eventId, 'type' => 'webhook',
                    'status' => 'received', 'amount' => $amount, 'payload' => $payload,
                ]);
            } catch (UniqueConstraintViolationException) {
                return 'duplicate'; // replayed webhook
            }

            if (bccomp($amount, (string) $payment->amount, 2) !== 0 || strtoupper($currency) !== strtoupper($payment->currency)) {
                $tx->update(['status' => 'amount_mismatch']);

                return 'rejected';
            }

            if ($payment->status === 'successful') {
                $tx->update(['status' => 'ignored']);

                return 'duplicate';
            }

            if ($success) {
                $this->orders->markPaid($payment, $eventId);
                $tx->update(['status' => 'successful']);
            } else {
                $payment->update(['status' => 'failed']);
                $payment->order->update(['payment_status' => 'failed']);
                $tx->update(['status' => 'failed']);
            }

            return 'processed';
        });
    }
}
