<?php

namespace App\Console\Commands;

use App\Actions\ClosePayment;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:expire-stale')]
#[Description('Expire pending payments that were never started with a provider and are over three hours old')]
class ExpireStalePayments extends Command
{
    public const STALE_AFTER_HOURS = 3;

    public function handle(ClosePayment $closePayment): int
    {
        $expired = 0;

        Payment::query()
            ->pending()
            ->whereNull('provider_payment_id')
            ->where('created_at', '<=', now()->subHours(self::STALE_AFTER_HOURS))
            ->lazyById()
            ->each(function (Payment $payment) use ($closePayment, &$expired): void {
                $closed = $closePayment->handle($payment, PaymentStatus::Expired, notify: true);

                if ($closed->status === PaymentStatus::Expired) {
                    $expired++;
                }
            });

        $this->info("Expired {$expired} payments.");

        return self::SUCCESS;
    }
}
