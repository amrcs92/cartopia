<?php

namespace App\Payments;

use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\Outcomes\PaymentOutcome;

interface PaymentGateway
{
    /**
     * Opens a payment with the provider and says where to send the shopper.
     *
     * @throws PaymentProviderUnavailable
     * @throws PaymentProviderRejected
     */
    public function start(Payment $payment, PaymentRequest $request): StartedPayment;

    /**
     * Asks the provider what became of the payment, capturing it when needed.
     *
     * @throws PaymentProviderUnavailable
     * @throws PaymentProviderRejected
     */
    public function confirm(Payment $payment): PaymentOutcome;

    /**
     * Asks the provider what became of the payment without changing anything:
     * no capture, no expiry. For a payment Cartopia may already have closed.
     *
     * @throws PaymentProviderUnavailable
     * @throws PaymentProviderRejected
     */
    public function lookup(Payment $payment): PaymentOutcome;

    /**
     * Cancels the payment at the provider, or reports that it was paid after all.
     *
     * @throws PaymentProviderUnavailable
     * @throws PaymentProviderRejected
     */
    public function cancel(Payment $payment): PaymentOutcome;
}
