<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\StoreSetting;

/** Bank transfer: customer pays offline using the order reference; staff verify the transfer manually. */
class BankTransferGateway implements PaymentGateway
{
    public function code(): string
    {
        return 'bank_transfer';
    }

    public function label(): string
    {
        return 'Bank transfer';
    }

    public function description(): string
    {
        return 'Transfer the total to our bank account. Your order is processed once we verify the payment.';
    }

    public function isEnabled(string $audience): bool
    {
        return (bool) StoreSetting::get('payment_bank_enabled', '1');
    }

    public function initiate(Order $order, Payment $payment): void
    {
        $payment->update(['reference' => $order->reference]);
        $order->update(['payment_status' => 'pending']);
    }

    /** Instructions shown on the confirmation page / email; all values come from store settings. */
    public static function instructions(): array
    {
        return array_filter([
            'Bank' => StoreSetting::get('bank_name'),
            'Account title' => StoreSetting::get('bank_account_title'),
            'Account number / IBAN' => StoreSetting::get('bank_account_number'),
            'Instructions' => StoreSetting::get('bank_instructions'),
        ]);
    }
}
