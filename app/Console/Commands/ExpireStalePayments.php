<?php

namespace App\Console\Commands;

use App\Actions\ExpireStalePayment;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('payments:expire-stale')]
#[Description('Settle or expire pending payments over three hours old, asking the provider first')]
class ExpireStalePayments extends Command
{
    public const STALE_AFTER_HOURS = 3;

    public function handle(ExpireStalePayment $expireStalePayment): int
    {
        $expired = 0;
        $failed = 0;

        Payment::query()
            ->pending()
            ->whereNull('provider_payment_id')
            ->where('created_at', '<=', now()->subHours(self::STALE_AFTER_HOURS))
            ->lazyById()
            ->each(function (Payment $payment) use ($expireStalePayment, &$expired, &$failed): void {
                // One payment that fails must not hold up the others; it stays pending for the next run.
                try {
                    $closed = $expireStalePayment->handle($payment);

                    if ($closed->status === PaymentStatus::Expired) {
                        $expired++;
                    }
                } catch (PaymentProviderUnavailable|PaymentProviderRejected $e) {
                    // The message only: the previous exception can carry the provider's response.
                    Log::warning("Stale payment {$payment->id} left for the next run: {$e->getMessage()}");
                    $failed++;
                } catch (Throwable $e) {
                    report($e);
                    $failed++;
                }
            });

        $this->info("Expired {$expired} payments.");

        if ($failed > 0) {
            $this->warn("{$failed} payments could not be processed.");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
