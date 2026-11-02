<?php

use App\Actions\StartOrderPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Mail\PaymentProblemMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Outcomes\Declined;
use App\Payments\PaymentGateways;
use App\Payments\PayPalWebhookVerifier;
use App\Support\Money;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->fake = PaymentGateways::fake();
    config(['services.stripe.webhook_secret' => 'whsec_test']);
});

/**
 * A split order ($2.10 from the wallet, the rest hosted) with its payment pending.
 */
function splitPayment(PaymentMethod $method = PaymentMethod::Stripe): Payment
{
    [$user, $ups] = checkoutSetup(210);
    app(StartOrderPayment::class)->handle($user, $ups, $method, useWallet: true);

    return Payment::query()->latest('id')->firstOrFail();
}

function stripeExpiredFor(Payment $payment): void
{
    $json = json_encode([
        'id' => 'evt_1',
        'object' => 'event',
        'type' => 'checkout.session.expired',
        'data' => ['object' => ['id' => $payment->provider_reference, 'object' => 'checkout.session']],
    ]);

    test()->call('POST', route('webhooks.stripe'), [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => stripeSignature($json, 'whsec_test'),
        'CONTENT_TYPE' => 'application/json',
    ], $json)->assertOk();
}

it('emails when a payment expires or is denied while the shopper is away', function () {
    $stale = splitPayment();
    $stale->forceFill(['created_at' => now()->subHours(4)])->save();
    $this->artisan('payments:expire-stale')->assertSuccessful();
    Mail::assertQueued(PaymentProblemMail::class, 1);

    $webhook = splitPayment();
    stripeExpiredFor($webhook);
    Mail::assertQueued(PaymentProblemMail::class, 2);
    Mail::assertQueued(PaymentProblemMail::class, fn (PaymentProblemMail $mail) => $mail->hasTo($webhook->user->email) && $mail->afterCommit === true);

    $paypal = splitPayment(PaymentMethod::PayPal);
    $this->mock(PayPalWebhookVerifier::class, fn ($m) => $m->shouldReceive('verify')->andReturn(true));
    $json = json_encode(['id' => 'WH-1', 'event_type' => 'PAYMENT.CAPTURE.DENIED', 'resource' => ['id' => 'CAP1', 'custom_id' => (string) $paypal->id]]);
    $this->call('POST', route('webhooks.paypal'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $json)->assertOk();
    Mail::assertQueued(PaymentProblemMail::class, 3);

    // A repeat of the same event closes nothing, so it queues nothing.
    stripeExpiredFor($webhook);
    Mail::assertQueued(PaymentProblemMail::class, 3);
});

it('renders the order problem email with the returned wallet share', function () {
    $payment = splitPayment();
    stripeExpiredFor($payment);
    $payment = $payment->fresh();
    $mail = new PaymentProblemMail($payment);
    $html = $mail->render();
    $text = $mail->renderText();
    $order = $payment->order;

    expect($mail->envelope()->subject)->toBe("Payment Not Completed – Cartopia Order #{$order->id}")
        ->and($html)->toContain('font-weight:700;line-height:36px;">!</td>')
        ->and($html)->not->toContain('&amp;#')
        ->and($order->wallet_amount->format())->toBe('$2.10');

    foreach ([
        'Payment not completed',
        "We couldn't complete your payment for this order. Don't worry — your basket is saved, and you can try again whenever you're ready.",
        "#{$order->id} · {$order->total->format()}",
        'Nothing was charged.',
        '$2.10 has been restored to your wallet.',
        'Your basket is still there.',
        'Back to your basket',
        route('cart.show'),
    ] as $line) {
        expect($html)->toContain(e($line))->and($text)->toContain($line);
    }
});

it('leaves out the restored tick when the wallet paid nothing', function () {
    $order = Order::factory()->awaitingPayment()->create();
    $payment = Payment::factory()->forOrder($order)->stripe()->create(['status' => PaymentStatus::Expired]);
    $html = (new PaymentProblemMail($payment))->render();

    expect($html)->toContain('Nothing was charged.')
        ->and($html)->toContain('Your basket is still there.')
        ->and($html)->not->toContain('restored to your wallet');
});

it('renders the top-up problem email', function () {
    $user = User::factory()->withBalance(Money::fromCents(100))->create();
    $payment = Payment::factory()->topUp(2500)->stripe()->create(['user_id' => $user->id, 'status' => PaymentStatus::Expired]);
    $mail = new PaymentProblemMail($payment);
    $html = $mail->render();
    $text = $mail->renderText();

    expect($mail->envelope()->subject)->toBe('Payment Not Completed – Cartopia Top-up of $25.00')
        ->and($html)->toContain('Payment not completed')
        ->and($html)->toContain(e("We couldn't complete your payment for this top-up. Nothing was charged, and you can try again from your wallet whenever you're ready."))
        ->and($html)->toContain('Amount')
        ->and($html)->toContain('$25.00')
        ->and($html)->toContain('Nothing was charged.')
        ->and($html)->toContain('Go to your wallet')
        ->and($html)->toContain(route('wallet.show'))
        ->and($html)->not->toContain('basket is still there')
        ->and($text)->toContain("We couldn't complete your payment for this top-up.")
        ->and($text)->toContain(route('wallet.show'));
});

it('does not email for a cancel or a decline the shopper saw', function () {
    [$user, $payment] = startedOrderPayment();
    $this->actingAs($user)->get(route('payments.cancel', $payment));

    $second = splitPayment(PaymentMethod::PayPal);
    $this->fake->willConfirm(new Declined('INSTRUMENT_DECLINED'));
    $this->actingAs($second->user)->get(route('payments.return', $second));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and($second->fresh()->status)->toBe(PaymentStatus::Failed);
    Mail::assertNothingQueued();
});
