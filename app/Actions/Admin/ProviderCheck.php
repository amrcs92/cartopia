<?php

namespace App\Actions\Admin;

use App\Enums\ProviderCheckOutcome;

/**
 * The answer to an admin's Check with provider: what was found, and how to
 * say it to the admin.
 */
final readonly class ProviderCheck
{
    public function __construct(
        public ProviderCheckOutcome $outcome,
        public string $message,
    ) {}
}
