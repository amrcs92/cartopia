<?php

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\FakePaymentGateway;
use App\Payments\Outcomes\Cancelled;
use App\Payments\Outcomes\Declined;
use App\Payments\Outcomes\StillPending;
use App\Payments\Outcomes\Succeeded;
use App\Payments\PaymentGateway;
use App\Payments\PaymentGateways;
use App\Payments\PaymentRequest;
use App\Payments\SplitPlan;
use App\Support\Money;

function paymentRequestFor(Payment $payment): PaymentRequest
{
    return PaymentRequest::single('Top-up', $payment->amount, 'Top-up', 'https://cartopia.test/return', 'https://cartopia.test/cancel');
}

it('lists only configured methods', function () {
    config(['services.stripe.secret' => null, 'services.paypal.client_id' => null, 'services.paypal.secret' => null]);
    expect(app(PaymentGateways::class)->available())->toBe([]);

    config(['services.stripe.secret' => 'sk_test_x']);
    expect(app(PaymentGateways::class)->available())->toBe([PaymentMethod::Stripe]);

    config(['services.paypal.client_id' => 'id', 'services.paypal.secret' => 'secret']);
    expect(app(PaymentGateways::class)->available())->toBe([PaymentMethod::Stripe, PaymentMethod::PayPal]);
});

it('needs both PayPal credentials', function () {
    config(['services.stripe.secret' => null, 'services.paypal.client_id' => 'id', 'services.paypal.secret' => null]);

    expect(app(PaymentGateways::class)->available())->toBe([]);
});

it('shares the minimum charge with the split plan', function () {
    expect(PaymentGateways::MIN_CENTS)->toBe(SplitPlan::MIN_CENTS);
});

it('refuses the wallet as a gateway', function () {
    app(PaymentGateways::class)->for(PaymentMethod::Wallet);
})->throws(InvalidArgumentException::class);

it('refuses a method with no factory', function () {
    (new PaymentGateways([]))->for(PaymentMethod::Stripe);
})->throws(InvalidArgumentException::class);

it('builds a gateway lazily and once', function () {
    $built = 0;
    $registry = new PaymentGateways([
        'stripe' => function () use (&$built) {
            $built++;

            return new FakePaymentGateway;
        },
    ]);

    expect($built)->toBe(0);

    $first = $registry->for(PaymentMethod::Stripe);

    expect($registry->for(PaymentMethod::Stripe))->toBe($first)
        ->and($built)->toBe(1);
});

it('fakes both methods with one gateway', function () {
    $fake = PaymentGateways::fake();

    expect(app(PaymentGateways::class)->for(PaymentMethod::PayPal))->toBe($fake)
        ->and(app(PaymentGateways::class)->for(PaymentMethod::Stripe))->toBe($fake)
        ->and(app(PaymentGateways::class)->available())->toBe(PaymentMethod::hosted());
});

it('is a payment gateway', function () {
    expect(new FakePaymentGateway)->toBeInstanceOf(PaymentGateway::class);
});

it('starts with a reference and the return url', function () {
    $payment = Payment::factory()->stripe()->topUp(2500)->create();
    $fake = new FakePaymentGateway;
    $request = paymentRequestFor($payment);

    $started = $fake->start($payment, $request);

    expect($started->reference)->toBe("fake_{$payment->id}")
        ->and($started->redirectUrl)->toBe(url("/payments/{$payment->id}/return"))
        ->and($fake->started)->toHaveCount(1)
        ->and($fake->started[0][0]->is($payment))->toBeTrue()
        ->and($fake->started[0][1])->toBe($request);
});

it('redirects to the cancel url on request', function () {
    $payment = Payment::factory()->topUp(2500)->create();
    $fake = (new FakePaymentGateway)->redirectToCancel();

    expect($fake->start($payment, paymentRequestFor($payment))->redirectUrl)
        ->toBe(url("/payments/{$payment->id}/cancel"));
});

it('fails the next start only', function () {
    $payment = Payment::factory()->topUp(2500)->create();
    $fake = (new FakePaymentGateway)->failToStart();

    expect(fn () => $fake->start($payment, paymentRequestFor($payment)))
        ->toThrow(PaymentProviderUnavailable::class);

    expect($fake->start($payment, paymentRequestFor($payment))->reference)->toBe("fake_{$payment->id}");
});

it('confirms a Stripe payment with a visa card by default', function () {
    $payment = Payment::factory()->stripe()->topUp(2500)->create();
    $fake = new FakePaymentGateway;

    $outcome = $fake->confirm($payment);

    expect($outcome)->toBeInstanceOf(Succeeded::class)
        ->and($outcome->providerPaymentId)->toBe("fake_pay_{$payment->id}")
        ->and($outcome->amount->cents())->toBe(2500)
        ->and($outcome->currency)->toBe('usd')
        ->and($outcome->cardBrand)->toBe('visa')
        ->and($outcome->cardLast4)->toBe('4242')
        ->and($fake->confirmed)->toBe([$payment->id]);
});

it('confirms a PayPal payment without card details', function () {
    $payment = Payment::factory()->paypal()->topUp(2500)->create();

    $outcome = (new FakePaymentGateway)->confirm($payment);

    expect($outcome)->toBeInstanceOf(Succeeded::class)
        ->and($outcome->cardBrand)->toBeNull()
        ->and($outcome->cardLast4)->toBeNull();
});

it('confirms an order payment for its hosted amount', function () {
    $order = Order::factory()->create();
    $payment = Payment::factory()->forOrder($order)->create();

    $outcome = (new FakePaymentGateway)->confirm($payment);

    expect($outcome->amount)->toEqual($payment->amount);
});

it('returns queued confirm outcomes in order, then the default', function () {
    $payment = Payment::factory()->stripe()->topUp(2500)->create();
    $fake = (new FakePaymentGateway)->willConfirm(new StillPending, new Declined('Card declined'));

    expect($fake->confirm($payment))->toBeInstanceOf(StillPending::class)
        ->and($fake->confirm($payment))->toBeInstanceOf(Declined::class)
        ->and($fake->confirm($payment))->toBeInstanceOf(Succeeded::class)
        ->and($fake->confirmed)->toHaveCount(3);
});

it('cancels with Cancelled by default and records it', function () {
    $payment = Payment::factory()->topUp(2500)->create();
    $fake = new FakePaymentGateway;

    expect($fake->cancel($payment))->toBeInstanceOf(Cancelled::class)
        ->and($fake->cancelled)->toBe([$payment->id]);
});

it('uses the queued cancel outcome once', function () {
    $payment = Payment::factory()->stripe()->topUp(2500)->create();
    $fake = (new FakePaymentGateway)->willCancelWith(new Succeeded('pi_1', Money::fromCents(2500), 'usd'));

    expect($fake->cancel($payment))->toBeInstanceOf(Succeeded::class)
        ->and($fake->cancel($payment))->toBeInstanceOf(Cancelled::class);
});

it('looks up nothing paid by default and records it', function () {
    $payment = Payment::factory()->topUp(2500)->create();
    $fake = new FakePaymentGateway;

    expect($fake->lookup($payment))->toBeInstanceOf(Cancelled::class)
        ->and($fake->lookedUp)->toBe([$payment->id])
        ->and($fake->confirmed)->toBeEmpty();
});

it('returns queued lookup outcomes and failures in order', function () {
    $payment = Payment::factory()->stripe()->topUp(2500)->create();
    $fake = new FakePaymentGateway;
    $fake->willLookUp($fake->paid($payment), new PaymentProviderUnavailable('down'));

    expect($fake->lookup($payment))->toEqual(new Succeeded("fake_pay_{$payment->id}", $payment->amount, 'usd', 'visa', '4242'))
        ->and(fn () => $fake->lookup($payment))->toThrow(PaymentProviderUnavailable::class)
        ->and($fake->lookup($payment))->toBeInstanceOf(Cancelled::class);
});
