<?php

namespace App\Payments\Outcomes;

final readonly class RefundRefused implements RefundOutcome
{
    public function __construct(public string $reason) {}
}
