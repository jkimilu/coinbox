<?php

namespace App\Filament\Merchant\Widgets;

use App\Support\MerchantMetrics;
use Carbon\CarbonImmutable;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/** Single-series bar chart: payments received per day. */
class PaymentsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Payments received';

    protected ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 8];

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days'];
    }

    public function getDescription(): ?string
    {
        $daily = (new MerchantMetrics(auth()->user()->merchant_id))->daily((int) $this->filter);

        return MerchantMetrics::kes(array_sum($daily), 2).' in total. Daily totals, Nairobi time.';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function dailyTotals(): array
    {
        return (new MerchantMetrics(auth()->user()->merchant_id))->daily((int) $this->filter);
    }

    protected function getData(): array
    {
        $daily = $this->dailyTotals();
        $format = (int) $this->filter > 31 ? 'j M' : 'D j';

        return [
            'datasets' => [[
                'label' => 'Received (KES)',
                'data' => array_values($daily),
                'backgroundColor' => '#2ca01c',
                'hoverBackgroundColor' => '#227f15',
                'borderRadius' => 4,
                'borderSkipped' => 'bottom',
                'maxBarThickness' => 28,
                'categoryPercentage' => 0.8,
                'barPercentage' => 0.9,
            ]],
            'labels' => array_map(fn ($d) => CarbonImmutable::parse($d)->format($format), array_keys($daily)),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            animation: { duration: 250 },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    displayColors: false,
                    padding: 10,
                    callbacks: {
                        label: (ctx) => 'KES ' + Number(ctx.parsed.y).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                    },
                },
            },
            scales: {
                x: { grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 12, color: '#7c8b81' } },
                y: {
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: 'rgba(124,139,129,0.15)' },
                    ticks: {
                        color: '#7c8b81',
                        maxTicksLimit: 5,
                        callback: (v) => v >= 1e6 ? (v / 1e6).toFixed(1).replace(/\.0$/, '') + 'M' : v >= 1e3 ? (v / 1e3).toFixed(0) + 'K' : v,
                    },
                },
            },
        }
        JS);
    }
}
