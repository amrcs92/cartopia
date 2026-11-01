<?php

use App\Actions\ClosePayment;
use App\Actions\CompletePayment;
use App\Actions\StartOrderPayment;
use App\Cart\Cart;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Payments\Outcomes\Succeeded;
use App\Payments\PaymentGateways;
use App\Support\Money;
use App\Wallet\Wallet;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->fake = PaymentGateways::fake();
});

/**
 * Starts a card payment for the checkoutSetup() basket ($12.74) with the
 * wallet switch on.
 *
 * @return array{0: User, 1: Payment}
 */
function splitOrderPayment(int $balance = 500): array
{
    [$user, $ups] = checkoutSetup($balance);

    app(StartOrderPayment::class)->handle($user, $ups, PaymentMethod::Stripe, useWallet: true);

    return [$user, Payment::sole()];
}

it('holds the wallet share when the payment starts', function () {
    [$user, $ups] = checkoutSetup(500);

    $this->actingAs($user)->post(route('checkout.store'), [
        'shipping_method_id' => $ups->id,
        'payment_method' => 'stripe',
        'use_wallet' => '1',
    ]);

    $order = Order::sole();
    $payment = Payment::sole();
    $purchase = WalletTransaction::query()->where('type', WalletTransactionType::Purchase)->sole();
    [, $request] = $this->fake->started[0];

    expect($order->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($order->total->cents())->toBe(1274)
        ->and($order->wallet_amount->cents())->toBe(500)
        ->and($order->isSplit())->toBeTrue()
        ->and($payment->amount->cents())->toBe(774)
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($user->fresh()->balance->cents())->toBe(0)
        ->and($purchase->amount->cents())->toBe(-500)
        ->and($purchase->balance_after->cents())->toBe(0)
        ->and($purchase->description)->toBe("Order #{$order->id}")
        ->and($purchase->order_id)->toBe($order->id)
        ->and($purchase->payment_id)->toBeNull()
        ->and($this->fake->started)->toHaveCount(1)
        ->and($request->lines)->toHaveCount(1)
        ->and($request->lines[0]->name)->toBe("Cartopia order #{$order->id} (after $5.00 from your wallet)")
        ->and($request->lines[0]->unitPrice->cents())->toBe(774)
        ->and($request->lines[0]->quantity)->toBe(1)
        ->and($request->deliveryFee->cents())->toBe(0)
        ->and($request->total()->cents())->toBe(774)
        ->and($request->description)->toBe("Cartopia order #{$order->id}")
        ->and($request->returnUrl)->toBe(url("/payments/{$payment->id}/return"))
        ->and($request->cancelUrl)->toBe(url("/payments/{$payment->id}/cancel"))
        // The basket is only emptied once the payment succeeds.
        ->and(Cart::forUser($user)->count())->toBe(3);
});

it('applies the fifty-cent rule', function () {
    [$user, $ups] = checkoutSetup(1250);

    $this->actingAs($user)->post(route('checkout.store'), [
        'shipping_method_id' => $ups->id,
        'payment_method' => 'paypal',
        'use_wallet' => true,
    ]);

    $order = Order::sole();
    [, $request] = $this->fake->started[0];

    expect($order->wallet_amount->cents())->toBe(1224)
        ->and(Payment::sole()->amount->cents())->toBe(50)
        ->and($user->fresh()->balance->cents())->toBe(26)
        ->and($request->lines[0]->name)->toBe("Cartopia order #{$order->id} (after $12.24 from your wallet)")
        ->and($request->total()->cents())->toBe(50);
});

it('pays everything by card when the switch is off', function () {
    [$user, $ups] = checkoutSetup(500);

    $this->actingAs($user)->post(route('checkout.store'), [
        'shipping_method_id' => $ups->id,
        'payment_method' => 'stripe',
        'use_wallet' => '0',
    ]);

    $order = Order::sole();
    [, $request] = $this->fake->started[0];

    expect($order->wallet_amount->cents())->toBe(0)
        ->and($order->isSplit())->toBeFalse()
        ->and(Payment::sole()->amount->cents())->toBe(1274)
        ->and($user->fresh()->balance->cents())->toBe(500)
        ->and(WalletTransaction::query()->where('type', WalletTransactionType::Purchase)->exists())->toBeFalse()
        // The order's own lines, as without a split.
        ->and($request->lines)->toHaveCount(2)
        ->and($request->deliveryFee->cents())->toBe(500)
        ->and($request->total()->cents())->toBe(1274);
});

it('returns the wallet share once when the payment closes', function () {
    [$user, $payment] = splitOrderPayment();
    expect($user->fresh()->balance->cents())->toBe(0);

    app(ClosePayment::class)->handle($payment, PaymentStatus::Cancelled, notify: false);
    app(ClosePayment::class)->handle($payment, PaymentStatus::Cancelled, notify: false);

    $order = $payment->order->fresh();
    $release = WalletTransaction::query()->where('type', WalletTransactionType::Release)->sole();

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($user->fresh()->balance->cents())->toBe(500)
        ->and($release->amount->cents())->toBe(500)
        ->and($release->balance_after->cents())->toBe(500)
        ->and($release->description)->toBe("Order #{$order->id} not paid, balance returned")
        ->and($release->order_id)->toBe($order->id)
        ->and($release->payment_id)->toBeNull();
});

it('returns nothing when a payment without a wallet share closes', function () {
    [$user, $payment] = startedOrderPayment();

    app(ClosePayment::class)->handle($payment, PaymentStatus::Expired, notify: false);

    expect($user->fresh()->balance->cents())->toBe(10000)
        ->and(WalletTransaction::query()->where('type', WalletTransactionType::Release)->exists())->toBeFalse();
});

it('returns the share when the provider is down', function () {
    [$user, $ups] = checkoutSetup(500);
    $this->fake->failToStart();

    $this->actingAs($user)
        ->post(route('checkout.store'), [
            'shipping_method_id' => $ups->id,
            'payment_method' => 'stripe',
            'use_wallet' => '1',
        ])
        ->assertRedirect(route('cart.show'));

    $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
        ->hasFlash('checkoutError', "We couldn't reach Stripe. Try again in a moment."));

    // The debit was rolled back with the order: only the seed row is left.
    expect($user->fresh()->balance->cents())->toBe(500)
        ->and(Order::count())->toBe(0)
        ->and(Payment::count())->toBe(0)
        ->and(WalletTransaction::count())->toBe(1)
        ->and(WalletTransaction::sole()->type)->toBe(WalletTransactionType::SignupBonus)
        ->and(Cart::forUser($user)->count())->toBe(3);
});

it('takes the share again when money arrives after closing', function () {
    [$user, $payment] = splitOrderPayment();
    app(ClosePayment::class)->handle($payment, PaymentStatus::Expired, notify: false);
    expect($user->fresh()->balance->cents())->toBe(500);

    $outcome = new Succeeded("fake_pay_{$payment->id}", Money::fromCents(774), 'usd', 'visa', '4242');
    app(CompletePayment::class)->handle($payment, $outcome);
    // A second delivery (a webhook after the return) takes nothing more.
    app(CompletePayment::class)->handle($payment, $outcome);

    $order = $payment->order->fresh();
    $purchases = WalletTransaction::query()->where('type', WalletTransactionType::Purchase)->orderBy('id')->get();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($order->status)->toBe(OrderStatus::Placed)
        ->and($user->fresh()->balance->cents())->toBe(0)
        ->and($purchases)->toHaveCount(2)
        ->and($purchases[1]->amount->cents())->toBe(-500)
        ->and($purchases[1]->description)->toBe("Order #{$order->id}")
        ->and($purchases[1]->order_id)->toBe($order->id)
        // Money wins, but the basket is left alone.
        ->and(Cart::forUser($user)->count())->toBe(3);
});

it('keeps the order placed and logs when the returned share was spent meanwhile', function () {
    Log::spy();
    [$user, $payment] = splitOrderPayment();
    app(ClosePayment::class)->handle($payment, PaymentStatus::Expired, notify: false);

    // The returned $5.00 is spent down to $1.00 before the money arrives.
    app(Wallet::class)->debit($user, Money::fromCents(400), WalletTransactionType::AdminAdjustment);
    expect($user->fresh()->balance->cents())->toBe(100);

    app(CompletePayment::class)->handle($payment, new Succeeded("fake_pay_{$payment->id}", Money::fromCents(774), 'usd', 'visa', '4242'));

    $order = $payment->order->fresh();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($order->status)->toBe(OrderStatus::Placed)
        ->and($user->fresh()->balance->cents())->toBe(100)
        ->and(WalletTransaction::query()->where('type', WalletTransactionType::Purchase)->count())->toBe(1);

    Log::shouldHaveReceived('critical')->once()->withArgs(fn (string $message, array $context): bool => $context === [
        'payment_id' => $payment->id,
        'order_id' => $order->id,
        'unrecovered_cents' => 500,
    ]);
});

it('says the share is back after a cancel', function () {
    [$user, $payment] = splitOrderPayment();
    $message = 'Payment cancelled. Nothing was charged. Your $5.00 wallet balance is back.';

    $this->actingAs($user)
        ->get(route('payments.cancel', $payment))
        ->assertRedirect(route('cart.show'));

    $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
        ->hasFlash('toast', ['type' => 'info', 'message' => $message])
        ->hasFlash('checkoutError', $message));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and($user->fresh()->balance->cents())->toBe(500);
});

it('keeps the plain cancel message without a wallet share', function () {
    [$user, $payment] = startedOrderPayment();

    $this->actingAs($user)
        ->get(route('payments.cancel', $payment))
        ->assertRedirect(route('cart.show'));

    $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
        ->hasFlash('checkoutError', 'Payment cancelled. Nothing was charged.'));
});

it('ignores use_wallet for wallet checkouts', function () {
    [$user, $ups] = checkoutSetup(10000);

    $response = $this->actingAs($user)->post(route('checkout.store'), [
        'shipping_method_id' => $ups->id,
        'payment_method' => 'wallet',
        'use_wallet' => '1',
    ]);

    $order = Order::sole();
    $purchase = WalletTransaction::query()->where('type', WalletTransactionType::Purchase)->sole();

    $response->assertRedirect(route('orders.show', $order));

    expect($order->status)->toBe(OrderStatus::Placed)
        ->and($order->payment_method)->toBe(PaymentMethod::Wallet)
        ->and($order->wallet_amount->cents())->toBe(1274)
        ->and($user->fresh()->balance->cents())->toBe(10000 - 1274)
        ->and($purchase->amount->cents())->toBe(-1274)
        ->and($purchase->description)->toBe("Order #{$order->id}")
        ->and(Payment::count())->toBe(0)
        ->and($this->fake->started)->toBe([])
        ->and(Cart::forUser($user)->count())->toBe(0);
});

it('refuses a use_wallet that is not a boolean', function () {
    [$user, $ups] = checkoutSetup(500);

    $this->actingAs($user)
        ->post(route('checkout.store'), [
            'shipping_method_id' => $ups->id,
            'payment_method' => 'stripe',
            'use_wallet' => 'please',
        ])
        ->assertSessionHasErrors('use_wallet');

    expect(Order::count())->toBe(0);
});
