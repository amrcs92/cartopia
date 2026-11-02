<?php

namespace App\Cart;

use App\Models\Product;
use App\Support\Money;

/**
 * An unavailable line (its product is hidden from the shop) stays in the
 * basket for display but contributes nothing: its line total is zero.
 */
final readonly class CartLine
{
    public function __construct(
        public Product $product,
        public int $quantity,
        public Money $unitPrice,
        public Money $lineTotal,
        public bool $available = true,
    ) {}
}
