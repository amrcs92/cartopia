<?php

use App\Actions\CompletePayment;
use App\Enums\PaymentMethod;
use App\Mail\TopUpReceiptMail;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Outcomes\Succeeded;
use App\Payments\PaymentGateways;
use App\Payments\PaymentWording;
use App\Support\Money;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    PaymentGateways::fake();
});

function paidTopUp(PaymentMethod $method = PaymentMethod::Stripe): Payment
{
    $user = User::factory()->withBalance(Money::fromCents(11092))->create();
    $payment = Payment::factory()->topUp(2500)->state(['user_id' => $user->id, 'method' => $method])->create();
    $outcome = $method === PaymentMethod::Stripe
        ? new Succeeded('pi_3QbMabcdefgh2Ac7', $payment->amount, 'usd', 'visa', '4242')
        : new Succeeded('CAP-TOPUP-1', $payment->amount, 'usd');

    app(CompletePayment::class)->handle($payment, $outcome);

    return $payment->fresh();
}

it('queues one top-up receipt', function () {
    $payment = paidTopUp();
    $outcome = new Succeeded('pi_3QbMabcdefgh2Ac7', $payment->amount, 'usd', 'visa', '4242');

    app(CompletePayment::class)->handle($payment, $outcome);
    app(CompletePayment::class)->handle($payment->fresh(), $outcome);

    Mail::assertQueued(TopUpReceiptMail::class, 1);
    Mail::assertQueued(TopUpReceiptMail::class, fn (TopUpReceiptMail $mail) => $mail->hasTo($payment->user->email));
});

it('renders the top-up receipt', function (PaymentMethod $method, string $paidWith) {
    $payment = paidTopUp($method);
    $mail = new TopUpReceiptMail($payment);
    $html = $mail->render();
    $text = $mail->renderText();
    $reference = PaymentWording::reference($payment)['short'];

    expect($mail->envelope()->subject)->toBe('You added $25.00 to your Cartopia wallet')
        ->and($html)->toContain('$25.00 is in your wallet')
        ->and($html)->toContain('Your top-up went through. It is ready to spend at checkout.')
        ->and($html)->toContain('Go to your wallet');

    foreach (['$25.00', $paidWith, $reference, 'New balance', '$135.92', route('wallet.show')] as $line) {
        expect($html)->toContain(e($line))->and($text)->toContain($line);
    }
})->with([
    'card' => [PaymentMethod::Stripe, 'Visa ending in 4242, through Stripe'],
    'paypal' => [PaymentMethod::PayPal, 'PayPal'],
]);
