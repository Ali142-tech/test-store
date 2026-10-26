# Payments: what works today and what is pending

| Method | Status | Notes |
|---|---|---|
| Cash on delivery | **Implemented** | Order stays `unpaid`; payment is marked paid when the order is delivered (or manually). Enabled per store setting; wholesale COD is off by default. |
| Bank transfer | **Implemented** | Order is `pending` payment with the order reference as transfer reference; instructions come from Store settings. Staff use *Verify bank transfer* on the order (records the bank reference, marks paid, confirms the order). |
| JazzCash | **Not implemented** | Needs a merchant account, sandbox credentials and the provider's API/hash specification. |
| Easypaisa | **Not implemented** | Same prerequisites. |
| Card gateway | **Not implemented** | Choose an eligible provider for your merchant account. Never store raw card data; use hosted checkout/redirect. |

Unconfigured methods are **not shown at checkout** (`PaymentManager` registers only COD and bank transfer), and checkout rejects any other method on the server.

## Plumbing already in place for gateways
- `App\Services\Payments\PaymentGateway` interface and `PaymentManager::register()`.
- `payments` / `payment_transactions` tables (attempts, provider reference, unique `(provider, event_id)` for webhook idempotency).
- `PaymentWebhookProcessor`: **idempotent** handling of an already signature-verified notification: validates order reference, amount and currency, rejects mismatches, ignores replays, records failed payments. Covered by `tests/Feature/PaymentWebhookTest.php`.

## To add a gateway (checklist)
1. Obtain merchant credentials and the provider docs; confirm eligibility.
2. Put credentials in `.env` (never in code) and `config/services.php`.
3. Implement `PaymentGateway` (`isEnabled()` must return false unless credentials exist) and register it in `AppServiceProvider::boot()`.
4. Add a webhook route that **verifies the provider signature first**, then calls `PaymentWebhookProcessor::process(...)`.
5. Test in the provider sandbox, including: duplicate callback, wrong amount, failed payment, callback arriving before the browser returns.
6. **Reconciliation:** schedule a command that queries the provider for orders left `pending` for >30 minutes and calls the processor with the provider's answer. Not built yet because it is provider-specific.
7. Refunds: `OrderService::refund()` records the refund and restocks; actually returning the money through a gateway API must be added in the adapter. Today the admin refunds money manually (bank/COD) and records it.
