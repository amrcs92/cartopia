<?php

namespace App\Cart;

use App\Support\Money;

final readonly class CartSummary
{
    /**
     * @param  list<CartLine>  $lines
     */
    public function __construct(
        public array $lines,
        public Money $subtotal,
    ) {}

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /**
     * Items that can be bought: lines of hidden products don't count.
     */
    public function totalQuantity(): int
    {
        return array_sum(array_map(fn (CartLine $line): int => $line->available ? $line->quantity : 0, $this->lines));
    }

    /**
     * The first line, in basket order, whose product is no longer sold.
     */
    public function firstUnavailable(): ?CartLine
    {
        foreach ($this->lines as $line) {
            if (! $line->available) {
                return $line;
            }
        }

        return null;
    }
}
