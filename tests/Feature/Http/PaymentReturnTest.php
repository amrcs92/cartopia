<?php

use App\Cart\Cart;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\User;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\Outcomes\Declined;
use App\Payments\Outcomes\StillPending;
use App\Payments\Outcomes\Succeeded;
use App\Payments\PaymentGateway;
use App\Payments\PaymentGateways;
use App\Support\Money;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->fake = PaymentGateways::fake();
});

it('places the order on return', function () {
    [$user, $payment] = startedOrderPayment();

    $this->actingAs($user)
        ->get(route('payments.return', $payment))
        ->assertRedirect(route('orders.show', $payment->order));

    $this->get(route('orders.show', $payment->order))
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('orderPlaced', 'Order placed. Your Stripe payment of $12.74 was approved.'));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Placed);
});

it('words a PayPal order', function () {
    [$user, $payment] = startedOrderPayment(PaymentMethod::PayPal);

    $this->actingAs($user)->get(route('payments.return', $payment));

    $this->get(route('orders.show', $payment->order))
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('orderPlaced', 'Order placed. Your PayPal payment of $12.74 was approved.'));
});

it('only redirects on a second return', function () {
    [$user, $payment] = startedOrderPayment();

    $this->actingAs($user)
        ->get(route('payments.return', $payment))
        ->assertRedirect(route('orders.show', $payment->order));
    $this->get(route('payments.return', $payment))
        ->assertRedirect(route('orders.show', $payment->order));

    expect($this->fake->confirmed)->toHaveCount(1);
});

it('closes a declined payment', function () {
    [$user, $payment] = startedOrderPayment(PaymentMethod::PayPal);
    $this->fake->willConfirm(new Declined('INSTRUMENT_DECLINED'));
    $message = "PayPal couldn't take the payment. Try again or pick another way to pay.";

    $this->actingAs($user)
        ->get(route('payments.return', $payment))
        ->assertRedirect(route('cart.show'));

    $this->get(route('cart.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('toast', ['type' => 'error', 'message' => $message])
            ->hasFlash('checkoutError', $message));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(Cart::forUser($user)->count())->toBe(3);
});

it('words a failed card payment', function () {
    [$user, $payment] = startedOrderPayment();
    $this->fake->willConfirm(new Declined('amount_mismatch'));

    $this->actingAs($user)->get(route('payments.return', $payment));

    $this->get(route('cart.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('checkoutError', "Stripe couldn't take the payment. Try again or pick another way to pay."));
});

it('waits on a pending payment', function () {
    [$user, $payment] = startedOrderPayment(PaymentMethod::PayPal);
    $this->fake->willConfirm(new StillPending('CAP1'));
    $message = "We're still waiting for the payment to be confirmed. Check your orders in a minute.";

    $this->actingAs($user)
        ->get(route('payments.return', $payment))
        ->assertRedirect(route('cart.show'));

    $this->get(route('cart.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('toast', ['type' => 'info', 'message' => $message])
            ->hasFlash('checkoutError', $message));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->provider_payment_id)->toBe('CAP1');
});

it('cancels and keeps the basket', function () {
    [$user, $payment] = startedOrderPayment();
    $message = 'Payment cancelled. Nothing was charged.';

    $this->actingAs($user)
        ->get(route('payments.cancel', $payment))
        ->assertRedirect(route('cart.show'));

    $this->get(route('cart.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('toast', ['type' => 'info', 'message' => $message]));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(Cart::forUser($user)->count())->toBe(3)
        ->and($this->fake->cancelled)->toBe([$payment->id]);
});

it('places the order when cancel finds it paid', function () {
    [$user, $payment] = startedOrderPayment();
    $this->fake->willCancelWith(new Succeeded('pay_1', Money::fromCents(1274), 'usd', 'visa', '4242'));

    $this->actingAs($user)
        ->get(route('payments.cancel', $payment))
        ->assertRedirect(route('orders.show', $payment->order));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Placed);
});

it('does not ask the provider about a payment that is already closed', function () {
    [$user, $payment] = startedOrderPayment();

    $this->actingAs($user)->get(route('payments.cancel', $payment));
    $this->get(route('payments.cancel', $payment))->assertRedirect(route('cart.show'));

    expect($this->fake->cancelled)->toHaveCount(1);
});

it('keeps the payment pending when the provider cannot be reached', function () {
    [$user, $payment] = startedOrderPayment();

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('confirm')->andThrow(new PaymentProviderUnavailable('down'));
    $gateways = Mockery::mock(PaymentGateways::class);
    $gateways->shouldReceive('for')->andReturn($gateway);
    app()->instance(PaymentGateways::class, $gateways);

    $message = "We couldn't reach Stripe. Try again in a moment.";

    $this->actingAs($user)
        ->get(route('payments.return', $payment))
        ->assertRedirect(route('cart.show'));

    // The basket page reads the gateways too, so give it the real fake back.
    PaymentGateways::fake();

    $this->get(route('cart.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('toast', ['type' => 'error', 'message' => $message])
            ->hasFlash('checkoutError', $message));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('hides other shoppers payments', function () {
    [, $payment] = startedOrderPayment();
    $other = User::factory()->create();

    $this->actingAs($other)->get(route('payments.return', $payment))->assertNotFound();
    $this->get(route('payments.cancel', $payment))->assertNotFound();

    expect($this->fake->confirmed)->toBe([])
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('completes after logging in again', function () {
    [$user, $payment] = startedOrderPayment();

    $this->get(route('payments.return', $payment))->assertRedirect(route('login'));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('payments.return', $payment));

    $this->get(route('payments.return', $payment))
        ->assertRedirect(route('orders.show', $payment->order));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});
