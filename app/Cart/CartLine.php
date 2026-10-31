<?php

namespace App\Cart;

use App\Models\Product;
use App\Support\Money;

final readonly class CartLine
{
    public function __construct(
        public Product $product,
        public int $quantity,
        public Money $unitPrice,
        public Money $lineTotal,
    ) {}
}
