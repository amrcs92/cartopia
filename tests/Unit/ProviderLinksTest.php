<?php

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Payments\ProviderLinks;

it('links a Stripe payment to the test dashboard', function () {
    $payment = new Payment(['method' => PaymentMethod::Stripe, 'provider_payment_id' => 'pi_123']);

    expect(ProviderLinks::for($payment))->toBe('https://dashboard.stripe.com/test/payments/pi_123');
});

it('links a PayPal capture to the sandbox activity page', function () {
    $payment = new Payment(['method' => PaymentMethod::PayPal, 'provider_payment_id' => 'CAP456']);

    expect(ProviderLinks::for($payment))->toBe('https://www.sandbox.paypal.com/activity/payment/CAP456');
});

it('has no link without a provider id', function (?string $id) {
    expect(ProviderLinks::for(new Payment(['method' => PaymentMethod::Stripe, 'provider_payment_id' => $id])))->toBeNull();
})->with([null, '']);
