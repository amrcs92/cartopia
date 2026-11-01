<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\Outcomes\Cancelled;
use App\Payments\Outcomes\StillPending;
use App\Payments\Outcomes\Succeeded;
use App\Payments\PaymentGateways;
use Illuminate\Support\Facades\DB;

/**
 * Closes a payment left pending too long (spec 4.8). The provider is asked
 * first, read-only and under the row lock: money it confirms wins (spec 6.3),
 * so a shopper who paid is never told nothing was charged.
 */
class ExpireStalePayment
{
    public function __construct(
        private readonly PaymentGateways $gateways,
        private readonly CompletePayment $completePayment,
        private readonly ClosePayment $closePayment,
    ) {}

    /**
     * @throws PaymentProviderUnavailable
     * @throws PaymentProviderRejected
     */
    public function handle(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            // Settled meanwhile, or a capture PayPal holds, which its webhook settles.
            if (! $locked->isPending() || $locked->provider_payment_id !== null) {
                return $locked;
            }

            // A payment that never reached a provider has nothing to ask about.
            $outcome = $locked->provider_reference === null
                ? new Cancelled
                : $this->gateways->for($locked->method)->lookup($locked);

            if ($outcome instanceof Succeeded) {
                return $this->completePayment->handle($locked, $outcome);
            }

            if ($outcome instanceof StillPending && $outcome->providerPaymentId !== null) {
                $locked->update(['provider_payment_id' => $outcome->providerPaymentId]);

                return $locked;
            }

            return $this->closePayment->handle($locked, PaymentStatus::Expired, notify: true);
        });
    }
}
