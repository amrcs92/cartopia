<?php

namespace App\Payments\Outcomes;

final readonly class StillPending implements PaymentOutcome
{
    public function __construct(public ?string $providerPaymentId = null) {}
}
