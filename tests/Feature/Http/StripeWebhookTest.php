<?php

use App\Actions\ClosePayment;
use App\Actions\StartOrderPayment;
use App\Cart\Cart;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Mail\OrderReceiptMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WalletTransaction;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\PaymentGateway;
use App\Payments\PaymentGateways;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['services.stripe.webhook_secret' => 'whsec_test']);

    $this->fake = PaymentGateways::fake();
    $this->order = Order::factory()->awaitingPayment()->create();
    $this->payment = Payment::factory()->forOrder($this->order)->stripe()->withReference('cs_test_1')->create();
});

function stripeEvent(string $type, string $session = 'cs_test_1'): string
{
    return json_encode([
        'id' => 'evt_1',
        'object' => 'event',
        'type' => $type,
        'data' => ['object' => ['id' => $session, 'object' => 'checkout.session']],
    ]);
}

function postStripeWebhook(string $json, ?string $signature = null): TestResponse
{
    return test()->call('POST', route('webhooks.stripe'), [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $signature ?? stripeSignature($json, 'whsec_test'),
        'CONTENT_TYPE' => 'application/json',
    ], $json);
}

it('completes a payment', function () {
    postStripeWebhook(stripeEvent('checkout.session.completed'))->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Placed);
});

it('ignores a duplicate event', function () {
    $json = stripeEvent('checkout.session.completed');

    postStripeWebhook($json)->assertOk();
    postStripeWebhook($json)->assertOk();

    expect($this->fake->confirmed)->toHaveCount(1);
});

it('expires a payment', function () {
    postStripeWebhook(stripeEvent('checkout.session.expired'))->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('rejects a bad signature', function () {
    $json = stripeEvent('checkout.session.completed');

    postStripeWebhook($json, stripeSignature($json, 'whsec_wrong'))->assertStatus(400);
    postStripeWebhook($json, 'garbage')->assertStatus(400);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($this->fake->confirmed)->toBeEmpty();
});

it('rejects every request when no webhook secret is configured', function () {
    config(['services.stripe.webhook_secret' => null]);
    $json = stripeEvent('checkout.session.completed');

    postStripeWebhook($json, stripeSignature($json, ''))->assertStatus(400);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('rejects an unreadable payload', function () {
    $json = 'not json';

    postStripeWebhook($json)->assertStatus(400);
});

it('ignores unknown sessions and events', function () {
    postStripeWebhook(stripeEvent('checkout.session.completed', 'cs_unknown'))->assertOk();
    postStripeWebhook(stripeEvent('customer.created'))->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($this->fake->confirmed)->toBeEmpty();
});

it('answers 503 when Stripe cannot be reached, so it retries', function () {
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('confirm')->andThrow(new PaymentProviderUnavailable('down'));
    $gateways = Mockery::mock(PaymentGateways::class);
    $gateways->shouldReceive('for')->andReturn($gateway);
    app()->instance(PaymentGateways::class, $gateways);

    postStripeWebhook(stripeEvent('checkout.session.completed'))->assertStatus(503);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('needs no CSRF token or login', function () {
    // Feature tests skip CSRF checks while the app runs as "testing".
    app()['env'] = 'production';

    $this->post(route('cart.items.store'))->assertStatus(419);

    postStripeWebhook(stripeEvent('checkout.session.completed'))->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('places an expired order once when a late completed event says it was paid', function () {
    Mail::fake();
    [$user, $ups] = checkoutSetup(500);
    app(StartOrderPayment::class)->handle($user, $ups, PaymentMethod::Stripe, useWallet: true);
    $payment = Payment::query()->latest('id')->firstOrFail();
    app(ClosePayment::class)->handle($payment, PaymentStatus::Expired, notify: false);
    expect($user->fresh()->balance->cents())->toBe(500);

    $this->fake->willLookUp($this->fake->paid($payment), $this->fake->paid($payment));
    $json = stripeEvent('checkout.session.completed', (string) $payment->provider_reference);

    postStripeWebhook($json)->assertOk();
    postStripeWebhook($json)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Placed)
        // The returned share is taken again, once.
        ->and($user->fresh()->balance->cents())->toBe(0)
        ->and(WalletTransaction::query()->where('type', WalletTransactionType::Purchase)->count())->toBe(2)
        ->and(Cart::forUser($user)->count())->toBe(3)
        ->and($this->fake->lookedUp)->toBe([$payment->id])
        ->and($this->fake->confirmed)->toBeEmpty();

    Mail::assertQueued(OrderReceiptMail::class, 1);
});

it('leaves a closed payment alone when Stripe does not confirm a late completed event', function () {
    app(ClosePayment::class)->handle($this->payment, PaymentStatus::Expired, notify: false);

    postStripeWebhook(stripeEvent('checkout.session.completed'))->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($this->fake->lookedUp)->toBe([$this->payment->id]);
});

it('answers 503 when Stripe cannot be reached for a late completed event', function () {
    app(ClosePayment::class)->handle($this->payment, PaymentStatus::Expired, notify: false);
    $this->fake->willLookUp(new PaymentProviderUnavailable('down'));

    postStripeWebhook(stripeEvent('checkout.session.completed'))->assertStatus(503);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Expired);
});
