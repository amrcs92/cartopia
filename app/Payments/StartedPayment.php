<?php

namespace App\Payments;

final readonly class StartedPayment
{
    public function __construct(
        public string $reference,
        public string $redirectUrl,
    ) {}
}
