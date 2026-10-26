<?php

namespace App\Filament\Widgets;

use App\Services\Reports\OrderMetrics;
use Filament\Widgets\ChartWidget;

class ChannelChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Retail vs wholesale (net revenue, 30 days)';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('view reports');
    }

    protected function getData(): array
    {
        $d = app(OrderMetrics::class)->byCustomerType(now()->subDays(29)->startOfDay());

        return [
            'datasets' => [['data' => [(float) ($d['retail'] ?? 0), (float) ($d['wholesale'] ?? 0)], 'backgroundColor' => ['#0f1115', '#5b3df5']]],
            'labels' => ['Retail', 'Wholesale'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
