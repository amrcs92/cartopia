<?php

use App\Models\Order;
use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\Outcomes\Cancelled;
use App\Payments\Outcomes\StillPending;
use App\Payments\Outcomes\Succeeded;
use App\Payments\PaymentLine;
use App\Payments\PaymentRequest;
use App\Payments\StripeGateway;
use App\Support\Money;
use Stripe\ApiRequestor;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\HttpClient\CurlClient;
use Tests\Support\FakeStripeHttpClient;

beforeEach(function () {
    $this->stripe = new FakeStripeHttpClient;
    ApiRequestor::setHttpClient($this->stripe);
    $this->gateway = new StripeGateway('sk_test_secret');
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

function stripePayment(): Payment
{
    return Payment::factory()->stripe()->topUp(1274)->withReference('cs_test_1')->create(['id' => 42]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sessionBody(array $overrides = []): array
{
    return array_merge([
        'id' => 'cs_test_1',
        'object' => 'checkout.session',
        'url' => 'https://checkout.stripe.com/c/pay/cs_test_1',
        'status' => 'open',
        'payment_status' => 'unpaid',
        'amount_total' => 1274,
        'currency' => 'usd',
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function paidSessionBody(): array
{
    return sessionBody([
        'status' => 'complete',
        'payment_status' => 'paid',
        'payment_intent' => [
            'id' => 'pi_123',
            'object' => 'payment_intent',
            'latest_charge' => [
                'id' => 'ch_1',
                'object' => 'charge',
                'payment_method_details' => ['type' => 'card', 'card' => ['brand' => 'visa', 'last4' => '4242']],
            ],
        ],
    ]);
}

it('creates a checkout session', function () {
    $this->freezeTime();
    $payment = stripePayment();
    $this->stripe->queue(200, sessionBody());
    $request = new PaymentRequest(
        [new PaymentLine('Apple', Money::fromCents(30), 2), new PaymentLine('Cheese Triangle', Money::fromCents(374), 1)],
        Money::fromCents(500),
        'Cartopia order #5',
        'https://cartopia.test/return',
        'https://cartopia.test/cancel',
    );

    $started = $this->gateway->start($payment, $request);

    $sent = $this->stripe->last();
    expect($sent['method'])->toBe('post')
        ->and($sent['url'])->toEndWith('/v1/checkout/sessions')
        ->and($sent['params']['mode'])->toBe('payment')
        ->and($sent['params']['payment_method_types'])->toBe(['card'])
        ->and($sent['params']['line_items'])->toHaveCount(3)
        ->and($sent['params']['line_items'][0]['price_data']['unit_amount'])->toBe(30)
        ->and($sent['params']['line_items'][0]['quantity'])->toBe(2)
        ->and($sent['params']['line_items'][2]['price_data']['product_data']['name'])->toBe('Delivery')
        ->and($sent['params']['line_items'][2]['price_data']['unit_amount'])->toBe(500)
        ->and($sent['params']['line_items'][2]['price_data']['currency'])->toBe('usd')
        ->and($sent['params']['client_reference_id'])->toBe('42')
        ->and($sent['params']['metadata'])->toBe(['payment_id' => '42', 'purpose' => 'top_up'])
        ->and($sent['params']['customer_email'])->toBe($payment->user->email)
        ->and($sent['params']['success_url'])->toBe('https://cartopia.test/return')
        ->and($sent['params']['cancel_url'])->toBe('https://cartopia.test/cancel')
        ->and(abs($sent['params']['expires_at'] - (now()->getTimestamp() + 1800)))->toBeLessThanOrEqual(1)
        ->and($this->stripe->header('Idempotency-Key'))->toBe("payment-42-{$payment->created_at->getTimestamp()}-start")
        ->and($started->reference)->toBe('cs_test_1')
        ->and($started->redirectUrl)->toBe('https://checkout.stripe.com/c/pay/cs_test_1');
});

it('sends a split as one line', function () {
    $payment = stripePayment();
    $this->stripe->queue(200, sessionBody());

    $this->gateway->start($payment, PaymentRequest::single('Cartopia order #5 (after $2.10 from your wallet)', Money::fromCents(1274), 'Order', 'https://cartopia.test/r', 'https://cartopia.test/c'));

    $items = $this->stripe->last()['params']['line_items'];
    expect($items)->toHaveCount(1)
        ->and($items[0]['price_data']['unit_amount'])->toBe(1274);
});

it('reads a paid session', function () {
    $payment = stripePayment();
    $this->stripe->queue(200, paidSessionBody());

    $outcome = $this->gateway->confirm($payment);

    expect($outcome)->toEqual(new Succeeded('pi_123', Money::fromCents(1274), 'usd', 'visa', '4242'))
        ->and($this->stripe->last()['method'])->toBe('get')
        ->and($this->stripe->last()['url'])->toContain('cs_test_1')
        ->and($this->stripe->last()['params']['expand'])->toBe(['payment_intent.latest_charge']);
});

it('reads open and expired sessions', function () {
    $payment = stripePayment();

    $this->stripe->queue(200, sessionBody());
    expect($this->gateway->confirm($payment))->toBeInstanceOf(StillPending::class);

    $this->stripe->queue(200, sessionBody(['status' => 'expired']));
    expect($this->gateway->confirm($payment))->toBeInstanceOf(Cancelled::class);
});

it('looks up a session without changing it', function () {
    $payment = stripePayment();
    $this->stripe
        ->queue(200, paidSessionBody())
        ->queue(200, sessionBody())
        ->queue(200, sessionBody(['status' => 'expired']));

    expect($this->gateway->lookup($payment))->toEqual(new Succeeded('pi_123', Money::fromCents(1274), 'usd', 'visa', '4242'))
        ->and($this->gateway->lookup($payment))->toBeInstanceOf(StillPending::class)
        ->and($this->gateway->lookup($payment))->toBeInstanceOf(Cancelled::class)
        ->and(array_column($this->stripe->requests, 'method'))->toBe(['get', 'get', 'get'])
        ->and($this->stripe->last()['url'])->toEndWith('/v1/checkout/sessions/cs_test_1')
        ->and($this->stripe->last()['params']['expand'])->toBe(['payment_intent.latest_charge']);
});

it('expires a session on cancel', function () {
    $payment = stripePayment();
    $this->stripe->queue(200, sessionBody(['status' => 'expired']));

    expect($this->gateway->cancel($payment))->toBeInstanceOf(Cancelled::class)
        ->and($this->stripe->last()['url'])->toEndWith('/v1/checkout/sessions/cs_test_1/expire')
        ->and($this->stripe->header('Idempotency-Key'))->toBe("payment-42-{$payment->created_at->getTimestamp()}-cancel");
});

it('reports a session that was already paid when cancelling', function () {
    $payment = stripePayment();
    $this->stripe
        ->queue(400, ['error' => ['type' => 'invalid_request_error', 'message' => 'Refused.']])
        ->queue(200, paidSessionBody());

    $outcome = $this->gateway->cancel($payment);

    expect($outcome)->toBeInstanceOf(Succeeded::class)
        ->and($outcome->providerPaymentId)->toBe('pi_123')
        ->and($this->stripe->requests)->toHaveCount(2)
        ->and($this->stripe->last()['params']['expand'])->toBe(['payment_intent.latest_charge']);
});

it('reports an already expired session as cancelled', function () {
    $payment = stripePayment();
    $this->stripe
        ->queue(400, ['error' => ['type' => 'invalid_request_error', 'message' => 'Refused.']])
        ->queue(200, sessionBody(['status' => 'expired']));

    expect($this->gateway->cancel($payment))->toBeInstanceOf(Cancelled::class);
});

it('rejects a refused expire when the session is still open', function () {
    $payment = stripePayment();
    $this->stripe
        ->queue(400, ['error' => ['type' => 'invalid_request_error', 'message' => 'Refused.']])
        ->queue(200, sessionBody());

    $this->gateway->cancel($payment);
})->throws(PaymentProviderRejected::class);

it('rejects a refused expire when the session cannot be retrieved', function () {
    $payment = stripePayment();
    $this->stripe
        ->queue(400, ['error' => ['type' => 'invalid_request_error', 'message' => 'No such checkout.session']])
        ->queue(404, ['error' => ['type' => 'invalid_request_error', 'message' => 'No such checkout.session']]);

    $this->gateway->cancel($payment);
})->throws(PaymentProviderRejected::class);

it('sends the order id for an order payment', function () {
    $order = Order::factory()->create();
    $payment = Payment::factory()->forOrder($order)->stripe()->withReference('cs_test_1')->create();
    $this->stripe->queue(200, sessionBody());

    $this->gateway->start($payment, PaymentRequest::single('Order', Money::fromCents(1274), 'Order', 'https://cartopia.test/r', 'https://cartopia.test/c'));

    $metadata = $this->stripe->last()['params']['metadata'];
    expect($metadata['order_id'])->toBe((string) $order->id)
        ->and($metadata['purpose'])->toBe('order')
        ->and($metadata['payment_id'])->toBe((string) $payment->id);
});

it('maps outages', function () {
    $payment = stripePayment();

    $this->stripe->queueConnectionError();
    expect(fn () => $this->gateway->confirm($payment))->toThrow(PaymentProviderUnavailable::class);

    $this->stripe->queue(503, ['error' => ['type' => 'api_error', 'message' => 'Down']]);
    expect(fn () => $this->gateway->confirm($payment))->toThrow(PaymentProviderUnavailable::class);
});

it('maps bad credentials to a rejection without leaking the secret', function () {
    $payment = stripePayment();
    $this->stripe->queue(401, ['error' => ['type' => 'invalid_request_error', 'message' => 'Invalid API Key provided: sk_test_***cret']]);

    try {
        $this->gateway->confirm($payment);
        $this->fail('Expected a rejection.');
    } catch (PaymentProviderRejected $e) {
        expect($e->getMessage())->not->toContain('sk_test_secret')
            ->and($e->getPrevious())->not->toBeNull();
    }
});

it('gives Stripe ten seconds and five to connect', function () {
    $http = CurlClient::instance()->setTimeout(80)->setConnectTimeout(30);

    new StripeGateway('sk_test_secret');

    expect($http->getTimeout())->toBe(10)
        ->and($http->getConnectTimeout())->toBe(5);
});

it('refuses live keys', function (string $key) {
    new StripeGateway($key);
})->with(['sk_live_x', 'rk_live_x'])->throws(LogicException::class);

it('verifies a webhook signature', function () {
    $payload = json_encode(['id' => 'evt_1', 'object' => 'event', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_1']]]);
    $t = time();
    $signature = "t={$t},v1=".hash_hmac('sha256', "{$t}.{$payload}", 'whsec_test');

    $event = StripeGateway::verifyWebhook($payload, $signature, 'whsec_test');

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event->type)->toBe('checkout.session.completed');

    expect(fn () => StripeGateway::verifyWebhook($payload, $signature, 'whsec_other'))->toThrow(SignatureVerificationException::class);
    expect(fn () => StripeGateway::verifyWebhook($payload, $signature, null))->toThrow(UnexpectedValueException::class);
    expect(fn () => StripeGateway::verifyWebhook($payload, $signature, ''))->toThrow(UnexpectedValueException::class);
});

it('looks up a session Stripe no longer knows as not paid', function () {
    $payment = stripePayment();
    $this->stripe->queue(404, ['error' => ['type' => 'invalid_request_error', 'code' => 'resource_missing', 'message' => 'No such checkout.session']]);

    expect($this->gateway->lookup($payment))->toBeInstanceOf(Cancelled::class);
});

it('still rejects a lookup Stripe refuses for another reason', function (int $status, array $error) {
    $payment = stripePayment();
    $this->stripe->queue($status, ['error' => $error]);

    expect(fn () => $this->gateway->lookup($payment))->toThrow(PaymentProviderRejected::class);
})->with([
    'not found, other code' => [404, ['type' => 'invalid_request_error', 'message' => 'Not found']],
    'bad key' => [401, ['type' => 'invalid_request_error', 'message' => 'Invalid API Key']],
]);
