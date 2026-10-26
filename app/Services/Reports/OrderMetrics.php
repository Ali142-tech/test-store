<?php

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Quote;
use App\Models\WholesaleApplication;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Business metrics with explicit definitions (also documented in docs/REPORTING.md).
 *
 * "Received" orders = money actually collected: payment_status in paid / partially_refunded / refunded / refund_due.
 * Unpaid, pending (e.g. unverified bank transfer, COD not yet delivered) and failed orders are NEVER counted as revenue.
 *
 *  Gross sales  = sum of goods value (subtotal) of received orders, before discounts
 *  Discounts    = sum of discount_total of received orders
 *  Refunds      = sum of refunded_total of received orders
 *  Net revenue  = sum of (grand_total - refunded_total) of received orders   (goods + shipping + tax - refunds)
 *  Outstanding  = sum of grand_total of live (not cancelled) orders still unpaid/pending
 */
class OrderMetrics
{
    public const RECEIVED = ['paid', 'partially_refunded', 'refunded', 'refund_due'];

    private function received(?CarbonInterface $from): Builder
    {
        return Order::query()->whereIn('payment_status', self::RECEIVED)
            ->when($from, fn ($q) => $q->where('placed_at', '>=', $from));
    }

    /** @return array{gross: string, discounts: string, refunds: string, net: string, orders: int} */
    public function sales(?CarbonInterface $from = null): array
    {
        $r = $this->received($from)->selectRaw('COALESCE(SUM(subtotal),0) g, COALESCE(SUM(discount_total),0) d, COALESCE(SUM(refunded_total),0) f, COALESCE(SUM(grand_total),0) t, COUNT(*) c')->first();

        return [
            'gross' => bcadd((string) $r->g, '0', 2), 'discounts' => bcadd((string) $r->d, '0', 2), 'refunds' => bcadd((string) $r->f, '0', 2),
            'net' => bcsub((string) $r->t, (string) $r->f, 2), 'orders' => (int) $r->c,
        ];
    }

    public function outstanding(): string
    {
        return bcadd((string) Order::query()->where('status', '!=', 'cancelled')->whereIn('payment_status', ['unpaid', 'pending'])->sum('grand_total'), '0', 2);
    }

    /** @return array<string,int> */
    public function ordersByStatus(?CarbonInterface $from = null): array
    {
        return Order::query()->when($from, fn ($q) => $q->where('placed_at', '>=', $from))->select('status', DB::raw('COUNT(*) c'))->groupBy('status')->pluck('c', 'status')->map(fn ($c) => (int) $c)->all();
    }

    /** @return array<string,string> customer_type => net revenue */
    public function byCustomerType(?CarbonInterface $from = null): array
    {
        return $this->received($from)->select('customer_type', DB::raw('SUM(grand_total - refunded_total) n'))->groupBy('customer_type')->pluck('n', 'customer_type')
            ->map(fn ($n) => bcadd((string) $n, '0', 2))->all();
    }

    /** Daily net revenue for the last $days days (placed date), zero-filled. @return array<string,string> */
    public function dailyRevenue(int $days = 30): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $rows = $this->received($from)->select(DB::raw('DATE(placed_at) d'), DB::raw('SUM(grand_total - refunded_total) n'))->groupBy('d')->pluck('n', 'd');

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i)->toDateString();
            $out[$day] = bcadd((string) ($rows[$day] ?? 0), '0', 2);
        }

        return $out;
    }

    public function lowStockCount(): int
    {
        return ProductVariant::where('is_active', true)->whereHas('product', fn ($q) => $q->where('type', 'simple'))->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count();
    }

    public function pendingWholesale(): int
    {
        return WholesaleApplication::where('status', 'pending')->count();
    }

    public function pendingQuotes(): int
    {
        return Quote::where('status', 'requested')->count();
    }
}
