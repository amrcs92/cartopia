<?php

namespace App\Filament\Widgets;

use App\Admin\SalesFigures;
use App\Filament\Resources\Payments\PaymentResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $figures = app(SalesFigures::class);
        $needsAttention = $figures->needsAttention();

        return [
            Stat::make('Sales today', $figures->today()->format()),
            Stat::make('Sales this week', $figures->thisWeek()->format()),
            Stat::make('Orders this week', $figures->ordersThisWeek()),
            Stat::make('Needs attention', $needsAttention)
                ->color($needsAttention > 0 ? 'warning' : null)
                ->url(PaymentResource::getUrl('index', ['tab' => 'needs-attention'])),
        ];
    }
}
