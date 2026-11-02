<?php

namespace App\Payments\Outcomes;

/**
 * The provider took the refund, whether the money has arrived or is on its way.
 */
final readonly class RefundAccepted implements RefundOutcome
{
    public function __construct(public string $providerRefundId) {}
}
