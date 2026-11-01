<?php

namespace App\Payments\Outcomes;

final readonly class Declined implements PaymentOutcome
{
    public function __construct(public string $reason) {}
}
