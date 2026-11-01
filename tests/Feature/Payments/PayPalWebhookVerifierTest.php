<?php

use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\PayPalWebhookVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

const PAYPAL_VERIFY = 'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature';
const PAYPAL_EVENT = '{"id":"WH-1","resource":{"amount":{"value":"1.10"}}}';

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    config([
        'services.paypal.client_id' => 'client-id',
        'services.paypal.secret' => 'client-secret',
        'services.paypal.webhook_id' => 'WEBHOOK-ID',
    ]);
});

/**
 * @return array<string, mixed>
 */
function paypalVerifyFake(mixed $verification): array
{
    return [
        'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'tok-123', 'expires_in' => 3600]),
        PAYPAL_VERIFY => $verification,
    ];
}

function paypalWebhookRequest(string $raw = PAYPAL_EVENT): Request
{
    return Request::create('/webhooks/paypal', 'POST', server: [
        'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
        'HTTP_PAYPAL_CERT_URL' => 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-1',
        'HTTP_PAYPAL_TRANSMISSION_ID' => 'tx-1',
        'HTTP_PAYPAL_TRANSMISSION_SIG' => 'sig-1',
        'HTTP_PAYPAL_TRANSMISSION_TIME' => '2026-11-01T10:00:00Z',
        'CONTENT_TYPE' => 'application/json',
    ], content: $raw);
}

it('sends the raw body unchanged', function () {
    Http::fake(paypalVerifyFake(Http::response(['verification_status' => 'SUCCESS'])));

    expect(app(PayPalWebhookVerifier::class)->verify(paypalWebhookRequest()))->toBeTrue();

    Http::assertSent(fn (ClientRequest $r) => $r->url() === PAYPAL_VERIFY
        && str_contains($r->body(), '"webhook_event":{"id":"WH-1","resource":{"amount":{"value":"1.10"}}}'));
    Http::assertSent(function (ClientRequest $r) {
        if ($r->url() !== PAYPAL_VERIFY) {
            return false;
        }
        $body = json_decode($r->body(), true);

        return $r->hasHeader('Authorization', 'Bearer tok-123')
            && $body['auth_algo'] === 'SHA256withRSA'
            && $body['cert_url'] === 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-1'
            && $body['transmission_id'] === 'tx-1'
            && $body['transmission_sig'] === 'sig-1'
            && $body['transmission_time'] === '2026-11-01T10:00:00Z'
            && $body['webhook_id'] === 'WEBHOOK-ID';
    });
});

it('reads success and failure', function (string $status, bool $expected) {
    Http::fake(paypalVerifyFake(Http::response(['verification_status' => $status])));

    expect(app(PayPalWebhookVerifier::class)->verify(paypalWebhookRequest()))->toBe($expected);
})->with([['SUCCESS', true], ['FAILURE', false]]);

it('fails without a webhook id', function () {
    config(['services.paypal.webhook_id' => null]);
    Http::fake();
    Log::spy();

    expect(app(PayPalWebhookVerifier::class)->verify(paypalWebhookRequest()))->toBeFalse();

    Http::assertNothingSent();
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $m) => str_contains($m, 'PAYPAL_WEBHOOK_ID'));
});

it('fails on a body that is empty or not JSON without calling PayPal', function (string $raw) {
    Http::fake();

    expect(app(PayPalWebhookVerifier::class)->verify(paypalWebhookRequest($raw)))->toBeFalse();

    Http::assertNothingSent();
})->with(['empty' => '', 'not json' => '{"id": ']);

it('returns false and logs only the status on a 4xx answer', function () {
    Http::fake(paypalVerifyFake(Http::response(['name' => 'INVALID_REQUEST', 'message' => 'secret detail'], 400)));
    Log::spy();

    expect(app(PayPalWebhookVerifier::class)->verify(paypalWebhookRequest()))->toBeFalse();

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $m) => str_contains($m, '400') && ! str_contains($m, 'secret detail'));
});

it('throws unavailable on a 5xx answer', function () {
    Http::fake(paypalVerifyFake(Http::response('', 503)));

    app(PayPalWebhookVerifier::class)->verify(paypalWebhookRequest());
})->throws(PaymentProviderUnavailable::class);

it('throws unavailable when PayPal cannot be reached', function () {
    Http::fake(paypalVerifyFake(fn () => throw new ConnectionException('down')));

    app(PayPalWebhookVerifier::class)->verify(paypalWebhookRequest());
})->throws(PaymentProviderUnavailable::class);

it('gives PayPal ten seconds and five to connect', function () {
    $timeouts = [];
    Http::fake(function (ClientRequest $r, array $options) use (&$timeouts) {
        $timeouts[$r->url()] = [$options['timeout'] ?? null, $options['connect_timeout'] ?? null];

        return str_ends_with($r->url(), '/token')
            ? Http::response(['access_token' => 'tok-123', 'expires_in' => 3600])
            : Http::response(['verification_status' => 'SUCCESS']);
    });

    app(PayPalWebhookVerifier::class)->verify(paypalWebhookRequest());

    expect($timeouts[PAYPAL_VERIFY])->toBe([10, 5]);
});
