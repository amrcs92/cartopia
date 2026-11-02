<?php

namespace App\Admin;

use App\Enums\OrderStatus;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Order;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * The dashboard's numbers. Only placed orders count, so cancelled, unpaid and
 * refunded orders stay out. Orders are dated by created_at in the app timezone.
 */
class SalesFigures
{
    public function today(): Money
    {
        return $this->sum(now()->startOfDay());
    }

    /** From Monday 00:00 to now. */
    public function thisWeek(): Money
    {
        return $this->sum(now()->startOfWeek(CarbonInterface::MONDAY));
    }

    public function ordersThisWeek(): int
    {
        return $this->placedSince(now()->startOfWeek(CarbonInterface::MONDAY))->count();
    }

    public function needsAttention(): int
    {
        return PaymentResource::needsAttention(PaymentResource::getEloquentQuery())->count();
    }

    /**
     * Daily placed-order totals in cents, oldest first, ending today.
     *
     * @return array<string, int> Y-m-d date => cents
     */
    public function daily(int $days = 30): array
    {
        $first = now()->startOfDay()->subDays($days - 1);

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $series[$first->addDays($i)->format('Y-m-d')] = 0;
        }

        $totals = $this->placedSince($first)
            ->toBase()
            ->selectRaw('DATE(created_at) as day, SUM(total) as cents')
            ->groupBy('day')
            ->pluck('cents', 'day');

        foreach ($totals as $day => $cents) {
            if (array_key_exists($day, $series)) {
                $series[$day] = (int) $cents;
            }
        }

        return $series;
    }

    private function sum(CarbonInterface $since): Money
    {
        return Money::fromCents((int) $this->placedSince($since)->toBase()->sum('total'));
    }

    /**
     * @return Builder<Order>
     */
    private function placedSince(CarbonInterface $since): Builder
    {
        return Order::query()
            ->where('status', OrderStatus::Placed)
            ->where('created_at', '>=', $since);
    }
}
