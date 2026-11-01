<?php

use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\Outcomes\Cancelled;
use App\Payments\Outcomes\Declined;
use App\Payments\Outcomes\StillPending;
use App\Payments\Outcomes\Succeeded;
use App\Payments\PaymentLine;
use App\Payments\PaymentRequest;
use App\Payments\PayPalGateway;
use App\Support\Money;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const PAYPAL = 'https://api-m.sandbox.paypal.com';

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    $this->gateway = new PayPalGateway('client-id', 'client-secret');
});

function paypalPayment(): Payment
{
    return Payment::find(42) ?? Payment::factory()->paypal()->topUp(1274)->withReference('ORDER1')->create(['id' => 42]);
}

/**
 * @return array<string, PromiseInterface>
 */
function paypalToken(): array
{
    return [PAYPAL.'/v1/oauth2/token' => Http::response(['access_token' => 'tok-123', 'expires_in' => 3600])];
}

function paypalRequest(?string $name = null): PaymentRequest
{
    return new PaymentRequest(
        [
            new PaymentLine($name ?? 'Red car', Money::fromCents(250), 2),
            new PaymentLine('Blue car', Money::fromCents(274), 1),
        ],
        Money::fromCents(500),
        'Order 5',
        'https://cartopia.test/return',
        'https://cartopia.test/cancel',
    );
}

function paypalOrderResponse(): PromiseInterface
{
    return Http::response([
        'id' => 'ORDER1',
        'links' => [
            ['rel' => 'self', 'href' => PAYPAL.'/v2/checkout/orders/ORDER1'],
            ['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER1'],
        ],
    ], 201);
}

/**
 * @return array<string, mixed>
 */
function captureBody(string $status = 'COMPLETED'): array
{
    return ['id' => 'ORDER1', 'purchase_units' => [['payments' => ['captures' => [
        ['id' => 'CAP1', 'status' => $status, 'amount' => ['currency_code' => 'USD', 'value' => '12.74']],
    ]]]]];
}

it('fetches a token once', function () {
    Http::fake(paypalToken() + [PAYPAL.'/v2/checkout/orders' => paypalOrderResponse()]);

    $this->gateway->start(paypalPayment(), paypalRequest());
    $this->gateway->start(paypalPayment(), paypalRequest());

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $r) => $r->url() === PAYPAL.'/v1/oauth2/token'
        && $r['grant_type'] === 'client_credentials'
        && $r->hasHeader('Authorization', 'Basic '.base64_encode('client-id:client-secret')));
});

it('creates an order', function () {
    Http::fake(paypalToken() + [PAYPAL.'/v2/checkout/orders' => paypalOrderResponse()]);

    $started = $this->gateway->start(paypalPayment(), paypalRequest());

    expect($started->reference)->toBe('ORDER1')
        ->and($started->redirectUrl)->toBe('https://www.sandbox.paypal.com/checkoutnow?token=ORDER1');

    Http::assertSent(function (Request $r) {
        if ($r->url() !== PAYPAL.'/v2/checkout/orders') {
            return false;
        }

        $unit = $r['purchase_units'][0];

        return $r->hasHeader('Authorization', 'Bearer tok-123')
            && $r->hasHeader('PayPal-Request-Id', 'payment-42-start')
            && $r['intent'] === 'CAPTURE'
            && $unit['custom_id'] === '42'
            && $unit['amount']['currency_code'] === 'USD'
            && $unit['amount']['value'] === '12.74'
            && $unit['amount']['breakdown']['item_total'] === ['currency_code' => 'USD', 'value' => '7.74']
            && $unit['amount']['breakdown']['shipping'] === ['currency_code' => 'USD', 'value' => '5.00']
            && $unit['items'][0] === [
                'name' => 'Red car',
                'quantity' => '2',
                'unit_amount' => ['currency_code' => 'USD', 'value' => '2.50'],
            ]
            && $unit['items'][1]['quantity'] === '1'
            && $unit['items'][1]['unit_amount']['value'] === '2.74'
            && $r['payment_source']['paypal']['experience_context'] === [
                'return_url' => 'https://cartopia.test/return',
                'cancel_url' => 'https://cartopia.test/cancel',
                'brand_name' => 'Cartopia',
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
            ];
    });
});

it('fails when there is no payer-action link', function () {
    Http::fake(paypalToken() + [PAYPAL.'/v2/checkout/orders' => Http::response(['id' => 'ORDER1', 'links' => []], 201)]);

    $this->gateway->start(paypalPayment(), paypalRequest());
})->throws(PaymentProviderRejected::class);

it('formats amounts for PayPal', function () {
    expect(PayPalGateway::amount(Money::fromCents(50)))->toBe('0.50')
        ->and(PayPalGateway::amount(Money::fromCents(123400)))->toBe('1234.00')
        ->and(PayPalGateway::amount(Money::fromCents(7)))->toBe('0.07');
});

it('cuts long item names', function () {
    Http::fake(paypalToken() + [PAYPAL.'/v2/checkout/orders' => paypalOrderResponse()]);

    $this->gateway->start(paypalPayment(), paypalRequest(str_repeat('é', 200)));

    Http::assertSent(fn (Request $r) => $r->url() === PAYPAL.'/v2/checkout/orders'
        && $r['purchase_units'][0]['items'][0]['name'] === str_repeat('é', 127));
});

it('captures a completed payment', function () {
    Http::fake(paypalToken() + [PAYPAL.'/v2/checkout/orders/ORDER1/capture' => Http::response(captureBody(), 201)]);

    $outcome = $this->gateway->confirm(paypalPayment());

    expect($outcome)->toBeInstanceOf(Succeeded::class)
        ->and($outcome->providerPaymentId)->toBe('CAP1')
        ->and($outcome->amount->cents())->toBe(1274)
        ->and($outcome->currency)->toBe('usd');

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/capture')
        && $r->hasHeader('PayPal-Request-Id', 'payment-42-capture'));
});

it('reads a held capture as pending', function () {
    Http::fake(paypalToken() + [PAYPAL.'/v2/checkout/orders/ORDER1/capture' => Http::response(captureBody('PENDING'), 201)]);

    $outcome = $this->gateway->confirm(paypalPayment());

    expect($outcome)->toBeInstanceOf(StillPending::class)
        ->and($outcome->providerPaymentId)->toBe('CAP1');
});

it('reads a decline', function () {
    Http::fake(paypalToken() + [PAYPAL.'/v2/checkout/orders/ORDER1/capture' => Http::response(['details' => [['issue' => 'INSTRUMENT_DECLINED']]], 422)]);

    $outcome = $this->gateway->confirm(paypalPayment());

    expect($outcome)->toBeInstanceOf(Declined::class)
        ->and($outcome->reason)->toBe('INSTRUMENT_DECLINED');
});

it('reads an order that was already captured', function () {
    Http::fake(paypalToken() + [
        PAYPAL.'/v2/checkout/orders/ORDER1/capture' => Http::response(['details' => [['issue' => 'ORDER_ALREADY_CAPTURED']]], 422),
        PAYPAL.'/v2/checkout/orders/ORDER1' => Http::response(captureBody()),
    ]);

    $outcome = $this->gateway->confirm(paypalPayment());

    expect($outcome)->toBeInstanceOf(Succeeded::class)
        ->and($outcome->providerPaymentId)->toBe('CAP1');

    Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->url() === PAYPAL.'/v2/checkout/orders/ORDER1');
});

it('reads an unapproved order as pending', function () {
    Http::fake(paypalToken() + [PAYPAL.'/v2/checkout/orders/ORDER1/capture' => Http::response(['details' => [['issue' => 'ORDER_NOT_APPROVED']]], 422)]);

    $outcome = $this->gateway->confirm(paypalPayment());

    expect($outcome)->toBeInstanceOf(StillPending::class)
        ->and($outcome->providerPaymentId)->toBeNull();
});

it('maps outages', function () {
    Http::fake(paypalToken() + [PAYPAL.'/v2/checkout/orders/ORDER1/capture' => fn () => throw new ConnectionException('timeout')]);
    expect(fn () => $this->gateway->confirm(paypalPayment()))->toThrow(PaymentProviderUnavailable::class);

    Http::fake(paypalToken() + [PAYPAL.'/v2/checkout/orders/ORDER1/capture' => Http::response([], 500)]);
    expect(fn () => $this->gateway->confirm(paypalPayment()))->toThrow(PaymentProviderUnavailable::class);
});

it('maps bad credentials and other client errors to a rejection', function () {
    Http::fake([PAYPAL.'/v1/oauth2/token' => Http::response(['error' => 'invalid_client'], 401)]);
    expect(fn () => $this->gateway->confirm(paypalPayment()))->toThrow(PaymentProviderRejected::class);

    Cache::flush();
    Http::fake(paypalToken() + [PAYPAL.'/v2/checkout/orders/ORDER1/capture' => Http::response(['details' => [['issue' => 'INVALID_RESOURCE_ID']]], 404)]);
    expect(fn () => $this->gateway->confirm(paypalPayment()))->toThrow(PaymentProviderRejected::class);
});

it('keeps the secret and token out of error messages', function () {
    Http::fake(paypalToken() + [PAYPAL.'/v2/checkout/orders/ORDER1/capture' => Http::response([], 500)]);

    try {
        $this->gateway->confirm(paypalPayment());
    } catch (PaymentProviderUnavailable $e) {
        expect($e->getMessage())->toContain('42')->not->toContain('client-secret')->not->toContain('tok-123');
    }
});

it('cancels without calling PayPal', function () {
    Http::fake();

    expect($this->gateway->cancel(paypalPayment()))->toBeInstanceOf(Cancelled::class);

    Http::assertNothingSent();
});
