<?php

namespace App\Payments;

use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\Outcomes\Cancelled;
use App\Payments\Outcomes\Declined;
use App\Payments\Outcomes\PaymentOutcome;
use App\Payments\Outcomes\StillPending;
use App\Payments\Outcomes\Succeeded;
use App\Support\Money;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PayPalGateway implements PaymentGateway
{
    /** Fixed in code so a live host can never be reached. */
    public const string BASE_URL = 'https://api-m.sandbox.paypal.com';

    /** Seconds. Calls run under row locks, so they must end well inside InnoDB's 50-second lock wait. */
    public const int TIMEOUT = 10;

    public const int CONNECT_TIMEOUT = 5;

    private const string TOKEN_KEY = 'paypal.token';

    /** PayPal's own issue codes for a refused payment. */
    private const array DECLINES = ['INSTRUMENT_DECLINED', 'TRANSACTION_REFUSED'];

    public function __construct(
        private readonly string $clientId,
        private readonly string $secret,
    ) {}

    /**
     * PayPal wants dollars with two decimals and no thousands separator.
     */
    public static function amount(Money $money): string
    {
        $cents = $money->cents();

        return sprintf('%s%d.%02d', $cents < 0 ? '-' : '', intdiv(abs($cents), 100), abs($cents) % 100);
    }

    public function start(Payment $payment, PaymentRequest $request): StartedPayment
    {
        $itemTotal = Money::zero();
        $items = [];

        foreach ($request->lines as $line) {
            $itemTotal = $itemTotal->add($line->unitPrice->multiply($line->quantity));
            $items[] = [
                'name' => mb_substr($line->name, 0, 127),
                'quantity' => (string) $line->quantity,
                'unit_amount' => $this->money($line->unitPrice),
            ];
        }

        $response = $this->call($payment, fn (PendingRequest $http): Response => $http
            ->withHeaders(['PayPal-Request-Id' => $payment->idempotencyKey('start')])
            ->post('/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'custom_id' => (string) $payment->id,
                    'description' => mb_substr($request->description, 0, 127),
                    'amount' => [
                        ...$this->money($itemTotal->add($request->deliveryFee)),
                        'breakdown' => [
                            'item_total' => $this->money($itemTotal),
                            'shipping' => $this->money($request->deliveryFee),
                        ],
                    ],
                    'items' => $items,
                ]],
                'payment_source' => ['paypal' => ['experience_context' => [
                    'return_url' => $request->returnUrl,
                    'cancel_url' => $request->cancelUrl,
                    'brand_name' => 'Cartopia',
                    'user_action' => 'PAY_NOW',
                    'shipping_preference' => 'NO_SHIPPING',
                ]]],
            ]));

        $href = null;

        foreach ((array) $response->json('links', []) as $link) {
            if (is_array($link) && ($link['rel'] ?? null) === 'payer-action') {
                $href = (string) ($link['href'] ?? '');
            }
        }

        if ($href === null || $href === '') {
            throw new PaymentProviderRejected("PayPal gave no payer-action link for payment {$payment->id}.");
        }

        return new StartedPayment((string) $response->json('id'), $href);
    }

    public function confirm(Payment $payment): PaymentOutcome
    {
        // A capture exists already (held by PayPal): read it. Posting the capture again
        // with the same request id could only replay the old answer.
        if ($payment->provider_payment_id !== null) {
            return $this->lookup($payment);
        }

        $response = $this->call($payment, fn (PendingRequest $http): Response => $http
            ->withHeaders(['PayPal-Request-Id' => $payment->idempotencyKey('capture')])
            ->withBody('{}', 'application/json')
            ->post("/v2/checkout/orders/{$payment->provider_reference}/capture"), [422]);

        if ($response->successful()) {
            return $this->outcome($response);
        }

        $issue = (string) $response->json('details.0.issue');

        if ($issue === 'ORDER_ALREADY_CAPTURED') {
            return $this->lookup($payment);
        }

        if ($issue === 'ORDER_NOT_APPROVED') {
            return new StillPending;
        }

        if (in_array($issue, self::DECLINES, true)) {
            return new Declined($issue);
        }

        throw new PaymentProviderRejected("PayPal rejected the capture for payment {$payment->id}, code ".($issue !== '' ? $issue : 'none').'.');
    }

    /**
     * Reads the order and its capture, if any. Never captures.
     */
    public function lookup(Payment $payment): PaymentOutcome
    {
        return $this->outcome($this->call($payment, fn (PendingRequest $http): Response => $http
            ->get("/v2/checkout/orders/{$payment->provider_reference}")));
    }

    /**
     * PayPal orders can't be voided before approval, and Cartopia never captures a closed payment.
     */
    public function cancel(Payment $payment): PaymentOutcome
    {
        return new Cancelled;
    }

    public function accessToken(): string
    {
        return $this->token(null);
    }

    private function token(?Payment $payment): string
    {
        $cached = Cache::get(self::TOKEN_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = $this->send($payment, fn (): Response => $this->http()
            ->withBasicAuth($this->clientId, $this->secret)
            ->asForm()
            ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']));

        $token = (string) $response->json('access_token');

        if ($token === '') {
            throw new PaymentProviderRejected($this->describe($payment, 'PayPal gave no access token').'.');
        }

        $ttl = (int) $response->json('expires_in', 0) - 60;

        if ($ttl > 0) {
            Cache::put(self::TOKEN_KEY, $token, $ttl);
        }

        return $token;
    }

    private function outcome(Response $response): PaymentOutcome
    {
        /** @var array<string, mixed>|null $capture */
        $capture = $response->json('purchase_units.0.payments.captures.0');

        if (! is_array($capture)) {
            return new StillPending;
        }

        $id = (string) ($capture['id'] ?? '');

        return match ($capture['status'] ?? null) {
            'COMPLETED' => new Succeeded(
                $id,
                Money::fromDollars((string) ($capture['amount']['value'] ?? '0')),
                strtolower((string) ($capture['amount']['currency_code'] ?? 'USD')),
            ),
            'PENDING' => new StillPending($id),
            default => new Declined((string) ($capture['status'] ?? 'UNKNOWN')),
        };
    }

    /**
     * @return array{currency_code: string, value: string}
     */
    private function money(Money $money): array
    {
        return ['currency_code' => 'USD', 'value' => self::amount($money)];
    }

    /**
     * Calls the REST API with the bearer token, mapping failures to the gateway exceptions.
     *
     * @param  Closure(PendingRequest): Response  $callback
     * @param  list<int>  $allow  statuses handed back to the caller instead of thrown
     */
    private function call(Payment $payment, Closure $callback, array $allow = []): Response
    {
        return $this->send($payment, fn (): Response => $callback(
            $this->http()->withToken($this->token($payment))->acceptJson()->asJson(),
        ), $allow);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)->timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT);
    }

    /**
     * @param  Closure(): Response  $callback
     * @param  list<int>  $allow
     */
    private function send(?Payment $payment, Closure $callback, array $allow = []): Response
    {
        try {
            $response = $callback();
        } catch (ConnectionException $e) {
            throw new PaymentProviderUnavailable($this->describe($payment, 'PayPal could not be reached').'.', 0, $e);
        }

        if ($response->successful() || in_array($response->status(), $allow, true)) {
            return $response;
        }

        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_KEY);
        }

        $code = (string) ($response->json('details.0.issue') ?? $response->json('name') ?? $response->json('error') ?? 'none');
        $detail = $this->describe($payment, 'PayPal failed').', HTTP '.$response->status().', code '.$code.'.';

        throw $response->serverError()
            ? new PaymentProviderUnavailable($detail, 0, $response->toException())
            : new PaymentProviderRejected($detail, 0, $response->toException());
    }

    private function describe(?Payment $payment, string $what): string
    {
        return $payment === null ? $what : "{$what} for payment {$payment->id}";
    }
}
