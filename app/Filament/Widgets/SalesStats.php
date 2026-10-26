<?php

namespace App\Filament\Widgets;

use App\Services\Reports\OrderMetrics;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Last 30 days';

    protected ?string $description = 'Revenue counts only received payments. Unpaid / pending orders are excluded and shown as "Outstanding".';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('view reports');
    }

    protected function getStats(): array
    {
        $m = app(OrderMetrics::class);
        $from = now()->subDays(29)->startOfDay();
        $s = $m->sales($from);
        $status = $m->ordersByStatus($from);

        return [
            Stat::make('Gross sales', Money::format($s['gross']))->description($s['orders'].' paid orders'),
            Stat::make('Discounts', Money::format($s['discounts'])),
            Stat::make('Refunds', Money::format($s['refunds']))->color('danger'),
            Stat::make('Net revenue', Money::format($s['net']))->description('incl. shipping & tax, after refunds')->color('success'),
            Stat::make('Outstanding payments', Money::format($m->outstanding()))->description('unpaid COD / pending transfers')->color('warning'),
            Stat::make('Pending orders', (string) ($status['pending'] ?? 0)),
            Stat::make('Completed', (string) ($status['delivered'] ?? 0)),
            Stat::make('Cancelled', (string) ($status['cancelled'] ?? 0)),
            Stat::make('Low-stock variants', (string) $m->lowStockCount())->color($m->lowStockCount() ? 'danger' : 'success'),
            Stat::make('Wholesale applications pending', (string) $m->pendingWholesale()),
            Stat::make('Quote requests pending', (string) $m->pendingQuotes()),
        ];
    }
}
