<?php

namespace App\Payments;

use App\Support\Money;

final readonly class SplitPlan
{
    public const int MIN_CENTS = 50;

    private function __construct(
        public Money $wallet,
        public Money $hosted,
    ) {}

    public static function for(Money $total, Money $balance, bool $useWallet): self
    {
        $none = new self(Money::zero(), $total);

        if (! $useWallet || $balance->cents() <= 0 || $balance->cents() >= $total->cents()) {
            return $none;
        }

        $hostedCents = max($total->cents() - $balance->cents(), self::MIN_CENTS);
        $walletCents = $total->cents() - $hostedCents;

        if ($walletCents <= 0) {
            return $none;
        }

        return new self(Money::fromCents($walletCents), Money::fromCents($hostedCents));
    }

    public function isSplit(): bool
    {
        return $this->wallet->cents() > 0;
    }
}
