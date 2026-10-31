<?php

namespace App\Payments;

use App\Support\Money;

final readonly class PaymentLine
{
    public function __construct(
        public string $name,
        public Money $unitPrice,
        public int $quantity,
    ) {}
}
