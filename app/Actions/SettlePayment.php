<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\Outcomes\Cancelled;
use App\Payments\Outcomes\Declined;
use App\Payments\Outcomes\StillPending;
use App\Payments\Outcomes\Succeeded;
use App\Payments\PaymentGateways;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Asks the provider what became of a pending payment and records the answer
 * (spec 4.4, step 3). The provider is asked under the row lock, so a payment
 * closed meanwhile (by the stale job, say) is never captured. A provider event
 * saying the money moved still reaches a closed payment, read-only.
 */
class SettlePayment
{
    public function __construct(
        private readonly PaymentGateways $gateways,
        private readonly CompletePayment $completePayment,
        private readonly ClosePayment $closePayment,
    ) {}

    /**
     * @param  bool  $moneyMoved  the caller is a provider event saying the money moved
     *                            (Stripe's completed session, PayPal's completed capture)
     *
     * @throws PaymentProviderUnavailable
     * @throws PaymentProviderRejected
     */
    public function handle(Payment $payment, bool $notifyOnFailure, bool $moneyMoved = false): Payment
    {
        return DB::transaction(function () use ($payment, $notifyOnFailure, $moneyMoved): Payment {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentStatus::Succeeded) {
                return $locked;
            }

            if (! $locked->isPending()) {
                return $moneyMoved ? $this->settleClosed($locked) : $locked;
            }

            $outcome = $this->gateways->for($locked->method)->confirm($locked);

            return match (true) {
                $outcome instanceof Succeeded => $this->completePayment->handle($locked, $outcome),
                $outcome instanceof Declined => $this->closePayment->handle($locked, PaymentStatus::Failed, $notifyOnFailure),
                $outcome instanceof Cancelled => $this->closePayment->handle($locked, PaymentStatus::Cancelled, false),
                $outcome instanceof StillPending => $this->keepPending($locked, $outcome),
                default => throw new LogicException('Unknown payment outcome '.$outcome::class.'.'),
            };
        });
    }

    /**
     * The payment was closed here, but the provider says money moved. It is only
     * looked up, never captured: money the provider confirms wins (spec 6.3).
     */
    private function settleClosed(Payment $payment): Payment
    {
        $outcome = $this->gateways->for($payment->method)->lookup($payment);

        return $outcome instanceof Succeeded ? $this->completePayment->handle($payment, $outcome) : $payment;
    }

    private function keepPending(Payment $payment, StillPending $outcome): Payment
    {
        if ($outcome->providerPaymentId !== null) {
            $payment->update(['provider_payment_id' => $outcome->providerPaymentId]);
        }

        return $payment;
    }
}
