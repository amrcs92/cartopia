<?php

namespace App\Actions\Admin;

use App\Actions\ClosePayment;
use App\Actions\CompletePayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\ProviderCheckOutcome;
use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\Outcomes\StillPending;
use App\Payments\Outcomes\Succeeded;
use App\Payments\PaymentGateways;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * An admin asks the provider about a payment (spec 5.4). It is the same
 * read-only lookup the stale-payment job makes, with the same outcomes, except
 * that a capture PayPal still holds is asked about too (a missed webhook is
 * why an admin would check) and a still-pending answer changes nothing.
 */
class CheckPaymentWithProvider
{
    public function __construct(
        private readonly PaymentGateways $gateways,
        private readonly CompletePayment $completePayment,
        private readonly ClosePayment $closePayment,
    ) {}

    /**
     * @return ProviderCheck what the provider answered, for the admin
     *
     * @throws PaymentProviderUnavailable
     * @throws PaymentProviderRejected
     */
    public function handle(Payment $payment): ProviderCheck
    {
        return DB::transaction(function () use ($payment): ProviderCheck {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                return new ProviderCheck(ProviderCheckOutcome::AlreadySettled, "This payment is already {$locked->status->value}.");
            }

            $provider = match ($locked->method) {
                PaymentMethod::Stripe => 'Stripe',
                PaymentMethod::PayPal => 'PayPal',
                PaymentMethod::Wallet => throw new LogicException('A wallet payment has no provider to check.'),
            };

            // A payment that never reached a provider has nothing to ask about.
            if ($locked->provider_reference === null) {
                return $this->close($locked, $provider);
            }

            $outcome = $this->gateways->for($locked->method)->lookup($locked);

            if ($outcome instanceof Succeeded) {
                $completed = $this->completePayment->handle($locked, $outcome);

                // A mismatched amount or currency closes the payment as failed instead.
                if ($completed->status !== PaymentStatus::Succeeded) {
                    return new ProviderCheck(ProviderCheckOutcome::AmountMismatch, "{$provider} reported a different amount. The payment is marked failed.");
                }

                return new ProviderCheck(ProviderCheckOutcome::Paid, $locked->purpose === PaymentPurpose::TopUp
                    ? "Paid at {$provider}. The top-up is credited."
                    : "Paid at {$provider}. The order is placed.");
            }

            if ($outcome instanceof StillPending) {
                if ($outcome->providerPaymentId !== null) {
                    $locked->update(['provider_payment_id' => $outcome->providerPaymentId]);
                }

                return new ProviderCheck(ProviderCheckOutcome::StillPending, "Still pending at {$provider}.");
            }

            return $this->close($locked, $provider);
        });
    }

    private function close(Payment $payment, string $provider): ProviderCheck
    {
        $this->closePayment->handle($payment, PaymentStatus::Expired, notify: true);

        return new ProviderCheck(ProviderCheckOutcome::Closed, "{$provider} no longer has this payment. It's closed.");
    }
}
