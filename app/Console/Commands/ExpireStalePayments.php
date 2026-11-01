<?php

namespace App\Console\Commands;

use App\Actions\ExpireStalePayment;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:expire-stale')]
#[Description('Settle or expire pending payments over three hours old, asking the provider first')]
class ExpireStalePayments extends Command
{
    public const STALE_AFTER_HOURS = 3;

    public function handle(ExpireStalePayment $expireStalePayment): int
    {
        $expired = 0;

        Payment::query()
            ->pending()
            ->whereNull('provider_payment_id')
            ->where('created_at', '<=', now()->subHours(self::STALE_AFTER_HOURS))
            ->lazyById()
            ->each(function (Payment $payment) use ($expireStalePayment, &$expired): void {
                $closed = $expireStalePayment->handle($payment);

                if ($closed->status === PaymentStatus::Expired) {
                    $expired++;
                }
            });

        $this->info("Expired {$expired} payments.");

        return self::SUCCESS;
    }
}
