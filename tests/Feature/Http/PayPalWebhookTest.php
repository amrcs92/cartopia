<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\Outcomes\StillPending;
use App\Payments\Outcomes\Succeeded;
use App\Payments\PaymentGateways;
use App\Payments\PayPalWebhookVerifier;
use App\Support\Money;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->fake = PaymentGateways::fake();
    $this->order = Order::factory()->awaitingPayment()->create();
    $this->payment = Payment::factory()->forOrder($this->order)->paypal()->withReference('PP-ORDER-1')->create();
});

function verifierSays(bool $verified): void
{
    test()->mock(PayPalWebhookVerifier::class, fn ($m) => $m->shouldReceive('verify')->andReturn($verified));
}

/**
 * @param  array<string, mixed>  $resource
 */
function paypalEvent(string $type, array $resource): string
{
    return json_encode(['id' => 'WH-1', 'event_type' => $type, 'resource' => $resource]);
}

function postPayPalWebhook(string $json): TestResponse
{
    return test()->call('POST', route('webhooks.paypal'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $json);
}

it('captures an approved order the shopper never returned from', function () {
    verifierSays(true);

    postPayPalWebhook(paypalEvent('CHECKOUT.ORDER.APPROVED', ['id' => 'PP-ORDER-1']))->assertOk();

    expect($this->fake->confirmed)->toHaveCount(1)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Placed);
});

it('captures once when the approval races the return', function () {
    verifierSays(true);
    $this->payment->delete();
    [$user, $payment] = startedOrderPayment(PaymentMethod::PayPal);

    $this->actingAs($user)->get(route('payments.return', $payment));
    postPayPalWebhook(paypalEvent('CHECKOUT.ORDER.APPROVED', ['id' => $payment->provider_reference]))->assertOk();

    expect($this->fake->confirmed)->toHaveCount(1)
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Placed)
        ->and(Order::query()->where('status', OrderStatus::Placed)->count())->toBe(1);
});

it('finishes a held capture', function () {
    verifierSays(true);
    $this->fake->willConfirm(
        new StillPending('CAP1'),
        new Succeeded('CAP1', Money::fromCents(1274), 'usd'),
    );
    $this->payment->delete();
    [$user, $payment] = startedOrderPayment(PaymentMethod::PayPal);

    $this->actingAs($user)->get(route('payments.return', $payment));
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);

    postPayPalWebhook(paypalEvent('PAYMENT.CAPTURE.COMPLETED', ['id' => 'CAP1', 'custom_id' => (string) $payment->id]))->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Placed);
});

it('closes a denied capture', function () {
    verifierSays(true);

    postPayPalWebhook(paypalEvent('PAYMENT.CAPTURE.DENIED', ['id' => 'CAP1', 'custom_id' => (string) $this->payment->id]))->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('finds the payment by order id when custom_id is missing', function () {
    verifierSays(true);

    postPayPalWebhook(paypalEvent('PAYMENT.CAPTURE.DENIED', [
        'id' => 'CAP1',
        'supplementary_data' => ['related_ids' => ['order_id' => 'PP-ORDER-1']],
    ]))->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Failed);
});

it('rejects an unverified event', function () {
    verifierSays(false);

    postPayPalWebhook(paypalEvent('CHECKOUT.ORDER.APPROVED', ['id' => 'PP-ORDER-1']))->assertStatus(400);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($this->fake->confirmed)->toBeEmpty();
});

it('answers 503 when PayPal cannot verify, so it retries', function () {
    $this->mock(PayPalWebhookVerifier::class, fn ($m) => $m->shouldReceive('verify')->andThrow(new PaymentProviderUnavailable('down')));

    postPayPalWebhook(paypalEvent('CHECKOUT.ORDER.APPROVED', ['id' => 'PP-ORDER-1']))->assertStatus(503);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($this->fake->confirmed)->toBeEmpty();
});

it('ignores unknown events, payments and bodies', function () {
    verifierSays(true);

    postPayPalWebhook(paypalEvent('CUSTOMER.DISPUTE.CREATED', ['id' => 'PP-ORDER-1']))->assertOk();
    postPayPalWebhook(paypalEvent('CHECKOUT.ORDER.APPROVED', ['id' => 'PP-UNKNOWN']))->assertOk();
    postPayPalWebhook('not json')->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($this->fake->confirmed)->toBeEmpty();
});

it('ignores a custom_id that points at a Stripe payment', function () {
    verifierSays(true);
    $stripe = Payment::factory()->forOrder(Order::factory()->awaitingPayment()->create())->stripe()->withReference('cs_1')->create();

    postPayPalWebhook(paypalEvent('PAYMENT.CAPTURE.DENIED', ['id' => 'CAP1', 'custom_id' => (string) $stripe->id]))->assertOk();

    expect($stripe->fresh()->status)->toBe(PaymentStatus::Pending);
});
