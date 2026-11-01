<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\Outcomes\Cancelled;
use App\Payments\Outcomes\Declined;
use App\Payments\Outcomes\Succeeded;
use App\Payments\PaymentGateways;
use Illuminate\Support\Facades\DB;

/**
 * Handles the shopper leaving the provider's page (spec 4.4). The provider is
 * asked under the row lock: a payment that turns out to be paid is completed,
 * so the stale job and a late cancel can never disagree (spec 6.3).
 */
class CancelPayment
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

            // A capture id means PayPal holds the money: it can't be cancelled from here,
            // and its webhook settles it. The shopper sees the still-waiting message.
            if (! $locked->isPending() || $locked->provider_payment_id !== null) {
                return $locked;
            }

            $outcome = $this->gateways->for($locked->method)->cancel($locked);

            return match (true) {
                $outcome instanceof Succeeded => $this->completePayment->handle($locked, $outcome),
                $outcome instanceof Cancelled, $outcome instanceof Declined => $this->closePayment->handle($locked, PaymentStatus::Cancelled, notify: false),
                // Still pending at the provider (a Stripe session complete but not yet paid): leave it open.
                default => $locked,
            };
        });
    }
}
