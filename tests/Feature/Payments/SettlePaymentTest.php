<?php

use App\Actions\SettlePayment;
use App\Cart\Cart;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\FakePaymentGateway;
use App\Payments\Outcomes\Cancelled;
use App\Payments\Outcomes\Declined;
use App\Payments\Outcomes\PaymentOutcome;
use App\Payments\Outcomes\StillPending;
use App\Payments\PaymentGateway;
use App\Payments\PaymentGateways;

beforeEach(function () {
    $this->fake = PaymentGateways::fake();
});

it('completes a payment the provider confirms', function () {
    [$user, $payment] = startedOrderPayment();

    $result = app(SettlePayment::class)->handle($payment, notifyOnFailure: false);

    expect($this->fake->confirmed)->toBe([$payment->id])
        ->and($result->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->fresh()->provider_payment_id)->toBe("fake_pay_{$payment->id}")
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Placed)
        ->and(Cart::forUser($user)->count())->toBe(0);
});

it('fails a declined payment', function () {
    [$user, $payment] = startedOrderPayment();
    $this->fake->willConfirm(new Declined('card_declined'));

    $result = app(SettlePayment::class)->handle($payment, notifyOnFailure: true);

    expect($result->status)->toBe(PaymentStatus::Failed)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(Cart::forUser($user)->count())->toBe(3);
});

it('cancels a payment the shopper abandoned', function () {
    [, $payment] = startedOrderPayment();
    $this->fake->willConfirm(new Cancelled);

    $result = app(SettlePayment::class)->handle($payment, notifyOnFailure: true);

    expect($result->status)->toBe(PaymentStatus::Cancelled)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('keeps a still-pending payment pending and stores its capture id', function () {
    [, $payment] = startedOrderPayment(PaymentMethod::PayPal);
    $this->fake->willConfirm(new StillPending('CAPTURE-1'));

    $result = app(SettlePayment::class)->handle($payment, notifyOnFailure: false);

    $payment->refresh();
    expect($result->status)->toBe(PaymentStatus::Pending)
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->provider_payment_id)->toBe('CAPTURE-1')
        ->and($payment->completed_at)->toBeNull()
        ->and($payment->order->status)->toBe(OrderStatus::AwaitingPayment);
});

it('keeps a still-pending payment unchanged without a capture id', function () {
    [, $payment] = startedOrderPayment();
    $this->fake->willConfirm(new StillPending);

    app(SettlePayment::class)->handle($payment, notifyOnFailure: false);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->provider_payment_id)->toBeNull();
});

it('never asks the provider about a payment that is not pending', function () {
    [, $payment] = startedOrderPayment();
    $payment->update(['status' => PaymentStatus::Expired, 'completed_at' => now()]);

    $result = app(SettlePayment::class)->handle($payment, notifyOnFailure: false);

    expect($this->fake->confirmed)->toBe([])
        ->and($result->status)->toBe(PaymentStatus::Expired);
});

it('lets provider errors through', function () {
    [, $payment] = startedOrderPayment();

    $down = new class extends FakePaymentGateway
    {
        public function confirm(Payment $payment): PaymentOutcome
        {
            throw new PaymentProviderUnavailable('Stripe is down.');
        }
    };
    app()->instance(PaymentGateways::class, new PaymentGateways([
        PaymentMethod::Stripe->value => fn (): PaymentGateway => $down,
    ]));

    try {
        app(SettlePayment::class)->handle($payment, notifyOnFailure: false);
        $this->fail('Expected the provider error to propagate.');
    } catch (PaymentProviderUnavailable) {
        expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
    }
});
