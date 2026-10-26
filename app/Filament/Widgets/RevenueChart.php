<?php

namespace App\Filament\Widgets;

use App\Services\Reports\OrderMetrics;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Net revenue by day';

    public ?string $filter = '30';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('view reports');
    }

    protected function getFilters(): ?array
    {
        return ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days'];
    }

    protected function getData(): array
    {
        $days = app(OrderMetrics::class)->dailyRevenue((int) $this->filter);

        return [
            'datasets' => [['label' => 'Net revenue', 'data' => array_map('floatval', array_values($days)), 'borderColor' => '#5b3df5', 'backgroundColor' => 'rgba(91,61,245,.1)', 'fill' => true]],
            'labels' => array_map(fn ($d) => date('d M', strtotime($d)), array_keys($days)),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
