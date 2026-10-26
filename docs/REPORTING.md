# Dashboard metric definitions

Implemented in `App\Services\Reports\OrderMetrics` (tested in `AdminWorkflowTest`).

A **received** order is one whose `payment_status` is `paid`, `partially_refunded`, `refunded` or `refund_due`. Unpaid, pending (unverified bank transfer, COD not yet delivered), failed and never-paid cancelled orders are **never** revenue.

| Metric | Definition |
|---|---|
| Gross sales | Sum of `subtotal` (goods, before discounts) of received orders |
| Discounts | Sum of `discount_total` of received orders |
| Refunds | Sum of `refunded_total` of received orders |
| Net revenue | Sum of `grand_total - refunded_total` of received orders (includes shipping and tax collected) |
| Outstanding payments | Sum of `grand_total` of non-cancelled orders still `unpaid`/`pending` |
| Pending / Completed / Cancelled | Count of orders by `status` in the period (placed date) |
| Retail vs wholesale | Net revenue split by `customer_type` |
| Top sellers | Units = quantity minus returned quantity, orders not cancelled, last 30 days |
| Low stock | Active simple-product variants with `stock_quantity <= low_stock_threshold` |

Periods are by order **placed** date. Cash-basis (paid date) reporting is not implemented.
