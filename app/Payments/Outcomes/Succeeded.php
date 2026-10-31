<?php

namespace App\Payments\Outcomes;

use App\Support\Money;

final readonly class Succeeded implements PaymentOutcome
{
    public function __construct(
        public string $providerPaymentId,
        public Money $amount,
        public string $currency,
        public ?string $cardBrand = null,
        public ?string $cardLast4 = null,
    ) {}
}
