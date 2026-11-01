<?php

use App\Actions\ClosePayment;
use App\Actions\CompletePayment;
use App\Cart\Cart;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\WalletTransaction;
use App\Payments\Outcomes\Succeeded;
use App\Payments\PaymentGateways;
use App\Support\Money;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->fake = PaymentGateways::fake();
});

function paidOutcome(Payment $payment, ?int $cents = null): Succeeded
{
    return new Succeeded("fake_pay_{$payment->id}", Money::fromCents($cents ?? $payment->amount->cents()), 'usd', 'visa', '4242');
}

/**
 * @return list<array{product_id: int|null, product_name: string, unit_price: int, quantity: int, line_total: int}>
 */
function itemSnapshot(Order $order): array
{
    return $order->items()->orderBy('id')->get()->map(fn (OrderItem $item): array => [
        'product_id' => $item->product_id,
        'product_name' => $item->product_name,
        'unit_price' => $item->unit_price->cents(),
        'quantity' => $item->quantity,
        'line_total' => $item->line_total->cents(),
    ])->all();
}

it('places the order and empties the basket', function () {
    [$user, $payment] = startedOrderPayment();

    $result = app(CompletePayment::class)->handle($payment, paidOutcome($payment));

    $payment->refresh();
    expect($result->id)->toBe($payment->id)
        ->and($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->provider_payment_id)->toBe("fake_pay_{$payment->id}")
        ->and($payment->card_brand)->toBe('visa')
        ->and($payment->card_last4)->toBe('4242')
        ->and($payment->completed_at)->not->toBeNull()
        ->and($payment->order->status)->toBe(OrderStatus::Placed)
        ->and(Cart::forUser($user)->count())->toBe(0)
        ->and($user->fresh()->balance->cents())->toBe(10000);
});

it('does nothing the second time', function () {
    [, $payment] = startedOrderPayment();

    app(CompletePayment::class)->handle($payment, paidOutcome($payment));
    $completedAt = $payment->fresh()->completed_at;
    $orderUpdatedAt = $payment->order->fresh()->updated_at;

    $this->travel(5)->minutes();
    app(CompletePayment::class)->handle($payment, paidOutcome($payment));

    expect($payment->fresh()->completed_at->equalTo($completedAt))->toBeTrue()
        ->and($payment->order->fresh()->updated_at->equalTo($orderUpdatedAt))->toBeTrue()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('refuses a mismatched amount', function () {
    Log::spy();
    [$user, $payment] = startedOrderPayment();

    $result = app(CompletePayment::class)->handle($payment, paidOutcome($payment, 1000));

    expect($result->status)->toBe(PaymentStatus::Failed)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->fresh()->completed_at)->not->toBeNull()
        ->and($payment->fresh()->provider_payment_id)->toBeNull()
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(Cart::forUser($user)->count())->toBe(3);

    Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context): bool => $context === [
        'payment_id' => $payment->id,
        'expected_cents' => 1274,
        'received_cents' => 1000,
        'currency' => 'usd',
    ]);
});

it('refuses a mismatched currency', function () {
    Log::spy();
    [, $payment] = startedOrderPayment();

    app(CompletePayment::class)->handle($payment, new Succeeded('fake_pay_1', $payment->amount, 'eur'));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Cancelled);

    Log::shouldHaveReceived('error')->once();
});

it('keeps the order as priced when the basket changes meanwhile', function () {
    [$user, $payment] = startedOrderPayment();
    $order = $payment->order;
    $snapshot = itemSnapshot($order);

    $cart = Cart::forUser($user);
    $cart->add(Product::factory()->create(['price' => 999]), 1);
    Product::query()->whereKey($snapshot[0]['product_id'])->update(['price' => 5000]);

    app(CompletePayment::class)->handle($payment, paidOutcome($payment));

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Placed)
        ->and(itemSnapshot($order))->toBe($snapshot)
        ->and($order->subtotal->cents())->toBe(774)
        ->and($order->total->cents())->toBe(1274)
        ->and(Cart::forUser($user)->count())->toBe(0);
});

it('completes after the product was deleted', function () {
    [, $payment] = startedOrderPayment();
    $order = $payment->order;
    $snapshot = itemSnapshot($order);

    Product::query()->whereKey($snapshot[0]['product_id'])->delete();

    app(CompletePayment::class)->handle($payment, paidOutcome($payment));

    $order->refresh();
    $items = itemSnapshot($order);
    expect($order->status)->toBe(OrderStatus::Placed)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($order->total->cents())->toBe(1274)
        ->and($items)->toHaveCount(2)
        ->and($items[0])->toBe([...$snapshot[0], 'product_id' => null])
        ->and($items[1])->toBe($snapshot[1]);
});

it('places an order whose payment was closed, but keeps the basket', function () {
    [$user, $payment] = startedOrderPayment();

    app(ClosePayment::class)->handle($payment, PaymentStatus::Expired, notify: false);
    expect($payment->order->fresh()->status)->toBe(OrderStatus::Cancelled);

    // The shopper starts a new basket after the order was given up.
    $cart = Cart::forUser($user);
    $cart->clear();
    $cart->add(Product::factory()->create(), 1);

    app(CompletePayment::class)->handle($payment, paidOutcome($payment));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->fresh()->provider_payment_id)->toBe("fake_pay_{$payment->id}")
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Placed)
        ->and(Cart::forUser($user)->count())->toBe(1);
});

it('credits the wallet when a top-up is paid', function () {
    $payment = Payment::factory()->topUp(1000)->stripe()->create();

    app(CompletePayment::class)->handle($payment, paidOutcome($payment));

    $row = WalletTransaction::query()->where('payment_id', $payment->id)->sole();

    expect($payment->user->fresh()->balance->cents())->toBe(1000)
        ->and($row->type)->toBe(WalletTransactionType::TopUp)
        ->and($row->description)->toBe('Top-up with Stripe')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('credits a top-up only once however often it completes', function () {
    $payment = Payment::factory()->topUp(1000)->create();

    app(CompletePayment::class)->handle($payment, paidOutcome($payment));
    app(CompletePayment::class)->handle($payment, paidOutcome($payment));

    expect($payment->user->fresh()->balance->cents())->toBe(1000)
        ->and(WalletTransaction::where('payment_id', $payment->id)->count())->toBe(1);
});

it('credits a top-up the provider confirms after it was closed', function () {
    $payment = Payment::factory()->topUp(1000)->create();
    app(ClosePayment::class)->handle($payment, PaymentStatus::Cancelled, notify: false);

    app(CompletePayment::class)->handle($payment, paidOutcome($payment));

    expect($payment->user->fresh()->balance->cents())->toBe(1000)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('closes a pending payment and cancels its order', function () {
    [$user, $payment] = startedOrderPayment();

    $result = app(ClosePayment::class)->handle($payment, PaymentStatus::Cancelled, notify: false);

    expect($result->status)->toBe(PaymentStatus::Cancelled)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and($payment->fresh()->completed_at)->not->toBeNull()
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(Cart::forUser($user)->count())->toBe(3);
});

it('leaves a settled payment alone when closing', function () {
    [, $payment] = startedOrderPayment();
    app(CompletePayment::class)->handle($payment, paidOutcome($payment));

    app(ClosePayment::class)->handle($payment, PaymentStatus::Expired, notify: false);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Placed);
});

it('closes only to cancelled, failed or expired', function () {
    [, $payment] = startedOrderPayment();

    app(ClosePayment::class)->handle($payment, PaymentStatus::Succeeded, notify: false);
})->throws(InvalidArgumentException::class);
