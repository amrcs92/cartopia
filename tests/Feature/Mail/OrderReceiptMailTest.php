<?php

use App\Actions\CompletePayment;
use App\Actions\PlaceOrder;
use App\Actions\StartOrderPayment;
use App\Enums\PaymentMethod;
use App\Mail\OrderReceiptMail;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Outcomes\Succeeded;
use App\Payments\PaymentGateways;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->fake = PaymentGateways::fake();
});

function settledOrder(PaymentMethod $method, bool $useWallet = false): Order
{
    [$user, $ups] = checkoutSetup($useWallet ? 210 : 10000);

    if ($method === PaymentMethod::Wallet) {
        return app(PlaceOrder::class)->handle($user, $ups);
    }

    app(StartOrderPayment::class)->handle($user, $ups, $method, useWallet: $useWallet);
    $payment = Payment::sole();

    app(CompletePayment::class)->handle($payment, new Succeeded('pi_3QaKabcdefgh9Fe1', $payment->amount, 'usd', 'visa', '4242'));

    return $payment->order->fresh();
}

it('queues a receipt for a wallet order', function () {
    [$user, $ups] = checkoutSetup();

    $this->actingAs($user)->post(route('checkout.store'), ['shipping_method_id' => $ups->id, 'payment_method' => 'wallet']);

    Mail::assertQueued(OrderReceiptMail::class, 1);
    Mail::assertQueued(OrderReceiptMail::class, fn (OrderReceiptMail $mail) => $mail->hasTo($user->email) && $mail->afterCommit === true);
});

it('queues one receipt for a card order despite duplicate settles', function () {
    [$user, $payment] = startedOrderPayment();
    $outcome = new Succeeded('pi_dup', $payment->amount, 'usd', 'visa', '4242');

    $this->actingAs($user)->get(route('payments.return', $payment));
    app(CompletePayment::class)->handle($payment->fresh(), $outcome);
    app(CompletePayment::class)->handle($payment->fresh(), $outcome);

    Mail::assertQueued(OrderReceiptMail::class, 1);
});

it('queues nothing when checkout fails before the order is placed', function () {
    [$user, $ups] = checkoutSetup(100);

    $this->actingAs($user)->post(route('checkout.store'), ['shipping_method_id' => $ups->id, 'payment_method' => 'wallet']);
    expect($user->fresh()->balance->cents())->toBe(100)
        ->and(Order::query()->count())->toBe(0);

    $this->fake->failToStart();
    [$other, $method] = checkoutSetup();
    $this->actingAs($other)->post(route('checkout.store'), ['shipping_method_id' => $method->id, 'payment_method' => 'stripe']);
    expect(Payment::query()->where('status', 'pending')->count())->toBe(0);

    Mail::assertNothingQueued();
});

it('renders the receipt', function (PaymentMethod $method, bool $useWallet, array $expected) {
    $order = settledOrder($method, $useWallet);
    $mail = new OrderReceiptMail($order);
    $html = $mail->render();
    $text = $mail->renderText();

    foreach ($order->items as $item) {
        expect($html)->toContain($item->product_name);
    }
    expect($mail->envelope()->subject)->toBe("Your Cartopia Order #{$order->id} Is Confirmed!")
        ->and($html)->toContain('Your order is confirmed!')
        ->and($html)->toContain("We've successfully received your order")
        ->and($html)->toContain('View your order')
        ->and($html)->toContain(route('orders.show', $order))
        ->and($html)->toContain($order->total->format())
        ->and($text)->toContain($order->total->format())
        ->and($text)->toContain(route('orders.show', $order));

    foreach ($expected as $line) {
        expect($html)->toContain($line)->and($text)->toContain($line);
    }
})->with([
    'wallet' => [PaymentMethod::Wallet, false, ['Cartopia wallet']],
    'card' => [PaymentMethod::Stripe, false, ['Visa ending in 4242, through Stripe', 'Payment ref.', 'Paid through Stripe']],
    'paypal' => [PaymentMethod::PayPal, false, ['PayPal', 'Paid through PayPal']],
    'split' => [PaymentMethod::Stripe, true, ['Cartopia wallet $2.10 + Visa ending in 4242 $10.64', 'Paid from wallet', '-$2.10', 'Wallet balance after', '$0.00']],
]);
