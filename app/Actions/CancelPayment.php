<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
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

            if (! $locked->isPending()) {
                return $locked;
            }

            $outcome = $this->gateways->for($locked->method)->cancel($locked);

            if ($outcome instanceof Succeeded) {
                return $this->completePayment->handle($locked, $outcome);
            }

            return $this->closePayment->handle($locked, PaymentStatus::Cancelled, notify: false);
        });
    }
}
