<?php

namespace App\Filament\Widgets;

use App\Admin\SalesFigures;
use App\Filament\Resources\Payments\PaymentResource;
use Filament\Support\Icons\Heroicon;
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
            Stat::make('Sales today', $figures->today()->format())
                ->icon(Heroicon::OutlinedBanknotes),
            Stat::make('Sales this week', $figures->thisWeek()->format())
                ->icon(Heroicon::OutlinedBanknotes),
            Stat::make('Orders this week', $figures->ordersThisWeek())
                ->icon(Heroicon::OutlinedShoppingBag),
            Stat::make('Needs attention', $needsAttention)
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color($needsAttention > 0 ? 'warning' : null)
                ->url(PaymentResource::getUrl('index', ['tab' => 'needs-attention'])),
        ];
    }
}
