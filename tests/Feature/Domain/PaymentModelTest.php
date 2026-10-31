<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Money;
use App\Wallet\Wallet;
use Illuminate\Database\QueryException;

it('belongs to a user and an order', function () {
    $order = Order::factory()->awaitingPayment()->create();
    $payment = Payment::factory()->forOrder($order)->stripe()->create();

    expect($payment->order->is($order))->toBeTrue()
        ->and($payment->user->is($order->user))->toBeTrue()
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->isPending())->toBeTrue()
        ->and($order->payments)->toHaveCount(1);
});

it('allows one payment per provider reference', function () {
    Payment::factory()->stripe()->withReference('cs_test_1')->create();
    Payment::factory()->stripe()->withReference('cs_test_1')->create();
})->throws(QueryException::class);

it('credits a payment to the ledger only once', function () {
    $payment = Payment::factory()->topUp(2500)->stripe()->create();
    $wallet = app(Wallet::class);
    $wallet->credit($payment->user, Money::fromCents(2500), WalletTransactionType::TopUp, 'Top-up by card', payment: $payment);
    $wallet->credit($payment->user, Money::fromCents(2500), WalletTransactionType::TopUp, 'Top-up by card', payment: $payment);
})->throws(QueryException::class);

it('reads existing orders as wallet orders', function () {
    $order = Order::factory()->create();

    expect($order->fresh()->payment_method)->toBe(PaymentMethod::Wallet)
        ->and($order->isSplit())->toBeFalse();
});

it('scopes placed orders', function () {
    Order::factory()->create();
    Order::factory()->awaitingPayment()->create();

    expect(Order::query()->placed()->count())->toBe(1);
});

it('computes the hosted amount and recognises a split order', function () {
    $order = Order::factory()->awaitingPayment()->create(['total' => 1000, 'wallet_amount' => 400]);
    $payment = Payment::factory()->forOrder($order)->paypal()->create();

    expect($payment->amount->cents())->toBe(600)
        ->and($payment->method)->toBe(PaymentMethod::PayPal)
        ->and($order->fresh()->payment_method)->toBe(PaymentMethod::Stripe)
        ->and($order->isSplit())->toBeTrue();
});

it('finds the succeeded payment and the pending scope', function () {
    $order = Order::factory()->awaitingPayment()->create();
    Payment::factory()->forOrder($order)->stripe()->create();
    $paid = Payment::factory()->forOrder($order)->stripe()->succeeded()->create();

    expect($order->succeededPayment->is($paid))->toBeTrue()
        ->and(Payment::query()->pending()->count())->toBe(1)
        ->and($paid->completed_at)->not->toBeNull();
});
