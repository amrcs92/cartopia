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

    public function totalQuantity(): int
    {
        return array_sum(array_map(fn (CartLine $line): int => $line->quantity, $this->lines));
    }
}
