<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Enums\WalletTransactionType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Payments\PaymentWording;
use App\Wallet\Wallet;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A refunded $122.74 order: $100.00 from the wallet and $22.74 through the provider.
 */
function refundedOrder(User $user, PaymentMethod $method = PaymentMethod::Stripe, RefundStatus $status = RefundStatus::Succeeded, ?string $last4 = '4242'): Order
{
    $order = Order::factory()->for($user)->create([
        'subtotal' => 12274, 'shipping_fee' => 0, 'total' => 12274,
        'payment_method' => $method, 'wallet_amount' => 10000, 'status' => OrderStatus::Refunded,
    ]);
    $payment = Payment::factory()->forOrder($order)->succeeded()->create([
        'method' => $method, 'provider_payment_id' => 'pi_1',
        'card_brand' => $last4 ? 'visa' : null, 'card_last4' => $last4,
    ]);
    Refund::factory()->for($order)->create([
        'payment_id' => $payment->id, 'status' => $status, 'provider_amount' => 2274, 'wallet_amount' => 10000,
    ]);

    return $order;
}

it('keeps a refunded order on the receipt page for its owner', function () {
    $user = User::factory()->create();
    $order = refundedOrder($user);

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.status', 'refunded')
            ->where('order.refund_lines', [
                'Refunded to Visa ending in 4242 · $22.74',
                'Refunded to your wallet · $100.00',
            ]));
});

it('still answers 404 for another shoppers refunded order', function () {
    $order = refundedOrder(User::factory()->create());

    $this->actingAs(User::factory()->create())->get(route('orders.show', $order))->assertNotFound();
});

it('lists a refunded order with its status', function () {
    $user = User::factory()->create();
    $order = refundedOrder($user);

    $this->actingAs($user)
        ->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $order->id)
            ->where('orders.data.0.status', 'refunded'));
});

it('words the refund lines for each method', function (PaymentMethod $method, ?string $last4, string $line) {
    $order = refundedOrder(User::factory()->create(), $method, last4: $last4);

    expect(PaymentWording::refundLines($order))->toBe([$line, 'Refunded to your wallet · $100.00']);
})->with([
    'card' => [PaymentMethod::Stripe, '4242', 'Refunded to Visa ending in 4242 · $22.74'],
    'Stripe without a card' => [PaymentMethod::Stripe, null, 'Refunded to Stripe · $22.74'],
    'PayPal' => [PaymentMethod::PayPal, null, 'Refunded to PayPal · $22.74'],
]);

it('leaves out the wallet line for a provider-only order and the provider line for a wallet-only one', function () {
    $user = User::factory()->create();
    $stripeOnly = refundedOrder($user);
    $stripeOnly->refund->update(['wallet_amount' => 0]);
    $walletOnly = Order::factory()->for($user)->create(['status' => OrderStatus::Refunded, 'total' => 5000, 'wallet_amount' => 5000]);
    Refund::factory()->for($walletOnly)->create(['status' => RefundStatus::Succeeded, 'wallet_amount' => 5000]);

    expect(PaymentWording::refundLines($stripeOnly->fresh()))->toBe(['Refunded to Visa ending in 4242 · $22.74'])
        ->and(PaymentWording::refundLines($walletOnly))->toBe(['Refunded to your wallet · $50.00']);
});

it('shows no refund lines unless the refund succeeded', function (RefundStatus $status) {
    $order = refundedOrder(User::factory()->create(), status: $status);

    expect(PaymentWording::refundLines($order))->toBe([]);
})->with([RefundStatus::Pending, RefundStatus::Failed]);

it('shows no refund lines on an order that was never refunded', function () {
    expect(PaymentWording::refundLines(Order::factory()->create()))->toBe([]);
});

it('shows the refund in the wallet ledger as Refund, Order and the amount', function () {
    $user = User::factory()->create();
    $order = refundedOrder($user);
    app(Wallet::class)->credit(
        $user, $order->refund->wallet_amount, WalletTransactionType::Refund,
        "Refund for order #{$order->id}", order: $order, refund: $order->refund,
    );

    $this->actingAs($user)
        ->get(route('wallet.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactions.data.0.type_label', 'Refund')
            ->where('transactions.data.0.description', "Order #{$order->id}")
            ->where('transactions.data.0.amount', '+$100.00'));
});
