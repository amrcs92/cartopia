<?php

use App\Actions\Admin\CheckPaymentWithProvider;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Mail\OrderReceiptMail;
use App\Mail\PaymentProblemMail;
use App\Mail\TopUpReceiptMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\Outcomes\Cancelled;
use App\Payments\Outcomes\Declined;
use App\Payments\Outcomes\StillPending;
use App\Payments\Outcomes\Succeeded;
use App\Payments\PaymentGateways;
use App\Support\Money;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->fake = PaymentGateways::fake();
});

function checkablePayment(PaymentMethod $method, bool $topUp, array $state = []): Payment
{
    $factory = Payment::factory()->state(['method' => $method])->withReference('ref_1');

    if ($topUp) {
        return $factory->topUp(2500)->create(['user_id' => User::factory()->create()->id] + $state);
    }

    return $factory->forOrder(Order::factory()->awaitingPayment()->create())->create($state);
}

dataset('providers', [
    'Stripe' => [PaymentMethod::Stripe, 'Stripe'],
    'PayPal' => [PaymentMethod::PayPal, 'PayPal'],
]);

it('places the order when the provider says an order payment is paid', function (PaymentMethod $method, string $name) {
    $payment = checkablePayment($method, topUp: false);
    $this->fake->willLookUp($this->fake->paid($payment));

    $answer = app(CheckPaymentWithProvider::class)->handle($payment);

    expect($answer)->toBe("Paid at {$name}. The order is placed.")
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Placed);
    Mail::assertQueued(OrderReceiptMail::class, 1);
})->with('providers');

it('credits the wallet when the provider says a top-up is paid', function (PaymentMethod $method, string $name) {
    $payment = checkablePayment($method, topUp: true);
    $this->fake->willLookUp($this->fake->paid($payment));

    $answer = app(CheckPaymentWithProvider::class)->handle($payment);

    expect($answer)->toBe("Paid at {$name}. The top-up is credited.")
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->user->fresh()->balance->cents())->toBe(2500);
    Mail::assertQueued(TopUpReceiptMail::class, 1);
})->with('providers');

it('closes an order payment the provider no longer has', function (PaymentMethod $method, string $name) {
    $payment = checkablePayment($method, topUp: false);
    $this->fake->willLookUp(new Cancelled);

    $answer = app(CheckPaymentWithProvider::class)->handle($payment);

    expect($answer)->toBe("{$name} no longer has this payment. It's closed.")
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Cancelled);
    Mail::assertQueued(PaymentProblemMail::class, 1);
})->with('providers');

it('closes a top-up the provider declined', function (PaymentMethod $method, string $name) {
    $payment = checkablePayment($method, topUp: true);
    $this->fake->willLookUp(new Declined('DENIED'));

    $answer = app(CheckPaymentWithProvider::class)->handle($payment);

    expect($answer)->toBe("{$name} no longer has this payment. It's closed.")
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($payment->user->fresh()->balance->cents())->toBe(0);
    Mail::assertQueued(PaymentProblemMail::class, 1);
})->with('providers');

it('changes nothing when the payment is still pending', function (PaymentMethod $method, string $name, bool $topUp) {
    $payment = checkablePayment($method, $topUp);
    $this->fake->willLookUp(new StillPending);

    $answer = app(CheckPaymentWithProvider::class)->handle($payment);

    expect($answer)->toBe("Still pending at {$name}.")
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->provider_payment_id)->toBeNull();
    Mail::assertNothingQueued();
})->with([
    'Stripe order' => [PaymentMethod::Stripe, 'Stripe', false],
    'Stripe top-up' => [PaymentMethod::Stripe, 'Stripe', true],
    'PayPal order' => [PaymentMethod::PayPal, 'PayPal', false],
    'PayPal top-up' => [PaymentMethod::PayPal, 'PayPal', true],
]);

it('records the capture id PayPal reports while still pending', function () {
    $payment = checkablePayment(PaymentMethod::PayPal, topUp: false);
    $this->fake->willLookUp(new StillPending('CAP1'));

    $answer = app(CheckPaymentWithProvider::class)->handle($payment);

    expect($answer)->toBe('Still pending at PayPal.')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->provider_payment_id)->toBe('CAP1');
});

it('asks the provider about a capture waiting on its webhook', function () {
    $payment = checkablePayment(PaymentMethod::PayPal, topUp: true, state: ['provider_payment_id' => 'CAP1']);
    $this->fake->willLookUp($this->fake->paid($payment));

    $answer = app(CheckPaymentWithProvider::class)->handle($payment);

    expect($answer)->toBe('Paid at PayPal. The top-up is credited.')
        ->and($this->fake->lookedUp)->toBe([$payment->id]);
});

it('closes a payment that never reached the provider without asking it', function (PaymentMethod $method, string $name) {
    $payment = checkablePayment($method, topUp: false, state: ['provider_reference' => null]);

    $answer = app(CheckPaymentWithProvider::class)->handle($payment);

    expect($answer)->toBe("{$name} no longer has this payment. It's closed.")
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($this->fake->lookedUp)->toBe([]);
    Mail::assertQueued(PaymentProblemMail::class, 1);
})->with('providers');

it('does not call the provider for a settled payment', function (PaymentStatus $status) {
    $payment = checkablePayment(PaymentMethod::Stripe, topUp: true, state: ['status' => $status]);

    $answer = app(CheckPaymentWithProvider::class)->handle($payment);

    expect($answer)->toBe("This payment is already {$status->value}.")
        ->and($payment->fresh()->status)->toBe($status)
        ->and($this->fake->lookedUp)->toBe([]);
    Mail::assertNothingQueued();
})->with([PaymentStatus::Succeeded, PaymentStatus::Expired, PaymentStatus::Cancelled]);

it('lets an unreachable provider propagate and leaves the payment untouched', function () {
    $payment = checkablePayment(PaymentMethod::Stripe, topUp: false);
    $this->fake->willLookUp(new PaymentProviderUnavailable('down'));

    expect(fn () => app(CheckPaymentWithProvider::class)->handle($payment))->toThrow(PaymentProviderUnavailable::class);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::AwaitingPayment);
    Mail::assertNothingQueued();
});

it('lets a rejection propagate and leaves the payment untouched', function () {
    $payment = checkablePayment(PaymentMethod::PayPal, topUp: true);
    $this->fake->willLookUp(new PaymentProviderRejected('no'));

    expect(fn () => app(CheckPaymentWithProvider::class)->handle($payment))->toThrow(PaymentProviderRejected::class);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
    Mail::assertNothingQueued();
});

it('says so when the provider reports a different amount on an order payment', function (PaymentMethod $method, string $name) {
    $payment = checkablePayment($method, topUp: false);
    $this->fake->willLookUp(new Succeeded('pay_1', Money::fromCents($payment->amount->cents() + 100), 'usd'));

    $answer = app(CheckPaymentWithProvider::class)->handle($payment);

    expect($answer)->toBe("{$name} reported a different amount. The payment is marked failed.")
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Cancelled);
    Mail::assertNothingQueued();
})->with('providers');

it('says so when the provider reports a different amount on a top-up', function (PaymentMethod $method, string $name) {
    $payment = checkablePayment($method, topUp: true);
    $this->fake->willLookUp(new Succeeded('pay_1', Money::fromCents(1), 'usd'));

    $answer = app(CheckPaymentWithProvider::class)->handle($payment);

    expect($answer)->toBe("{$name} reported a different amount. The payment is marked failed.")
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->user->fresh()->balance->cents())->toBe(0);
    Mail::assertNothingQueued();
})->with('providers');

it('refuses to check a wallet payment', function () {
    $payment = checkablePayment(PaymentMethod::Wallet, topUp: true);

    expect(fn () => app(CheckPaymentWithProvider::class)->handle($payment))->toThrow(LogicException::class);
});
