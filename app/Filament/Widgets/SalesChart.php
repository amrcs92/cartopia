<?php

namespace App\Filament\Widgets;

use App\Admin\SalesFigures;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class SalesChart extends ChartWidget
{
    protected ?string $heading = 'Sales, last 30 days';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $daily = app(SalesFigures::class)->daily(30);

        return [
            'datasets' => [
                [
                    'label' => 'Sales ($)',
                    'data' => array_map(fn (int $cents) => $cents / 100, array_values($daily)),
                ],
            ],
            'labels' => array_map(fn (string $day) => Carbon::parse($day)->format('M j'), array_keys($daily)),
        ];
    }
}
