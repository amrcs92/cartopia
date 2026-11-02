<?php

namespace App\Payments;

use App\Enums\PaymentMethod;
use App\Models\Payment;

/**
 * Where to look a payment up in the provider's sandbox dashboard.
 */
final class ProviderLinks
{
    public static function for(Payment $payment): ?string
    {
        $id = $payment->provider_payment_id;
        if ($id === null || $id === '') {
            return null;
        }

        return match ($payment->method) {
            PaymentMethod::Stripe => "https://dashboard.stripe.com/test/payments/{$id}",
            PaymentMethod::PayPal => "https://www.sandbox.paypal.com/activity/payment/{$id}",
            PaymentMethod::Wallet => null,
        };
    }
}
