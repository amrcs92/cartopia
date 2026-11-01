<?php

namespace App\Payments;

use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\Outcomes\Cancelled;
use App\Payments\Outcomes\PaymentOutcome;
use App\Payments\Outcomes\StillPending;
use App\Payments\Outcomes\Succeeded;
use App\Support\Money;
use Closure;
use LogicException;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\HttpClient\CurlClient;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeGateway implements PaymentGateway
{
    private const array EXPAND = ['payment_intent.latest_charge'];

    /** Seconds. Calls run under row locks, so they must end well inside InnoDB's 50-second lock wait. */
    private const int TIMEOUT = 10;

    private const int CONNECT_TIMEOUT = 5;

    private readonly StripeClient $client;

    public function __construct(string $secret)
    {
        if (str_starts_with($secret, 'sk_live_') || str_starts_with($secret, 'rk_live_')) {
            throw new LogicException('Cartopia only uses Stripe in test mode; live keys are refused.');
        }

        // The SDK's shared client, so a test's ApiRequestor::setHttpClient() still takes over.
        CurlClient::instance()->setTimeout(self::TIMEOUT)->setConnectTimeout(self::CONNECT_TIMEOUT);

        $this->client = new StripeClient($secret);
    }

    public function start(Payment $payment, PaymentRequest $request): StartedPayment
    {
        $lineItems = array_map(
            fn (PaymentLine $line): array => $this->lineItem($line->name, $line->unitPrice, $line->quantity),
            $request->lines,
        );

        if ($request->deliveryFee->cents() > 0) {
            $lineItems[] = $this->lineItem('Delivery', $request->deliveryFee, 1);
        }

        $metadata = ['payment_id' => (string) $payment->id, 'purpose' => $payment->purpose->value];

        if ($payment->order_id !== null) {
            $metadata['order_id'] = (string) $payment->order_id;
        }

        $session = $this->call($payment, fn (): Session => $this->client->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'client_reference_id' => (string) $payment->id,
            'metadata' => $metadata,
            'customer_email' => $payment->user->email,
            'success_url' => $request->returnUrl,
            'cancel_url' => $request->cancelUrl,
            'expires_at' => now()->addMinutes(30)->getTimestamp(),
        ], ['idempotency_key' => "payment-{$payment->id}-start"]));

        return new StartedPayment((string) $session->id, (string) $session->url);
    }

    public function confirm(Payment $payment): PaymentOutcome
    {
        return $this->outcome($this->retrieve($payment));
    }

    private function retrieve(Payment $payment): Session
    {
        return $this->call($payment, fn (): Session => $this->client->checkout->sessions->retrieve(
            (string) $payment->provider_reference,
            ['expand' => self::EXPAND],
        ));
    }

    public function cancel(Payment $payment): PaymentOutcome
    {
        try {
            $this->call($payment, fn (): Session => $this->client->checkout->sessions->expire(
                (string) $payment->provider_reference,
                [],
                ['idempotency_key' => "payment-{$payment->id}-cancel"],
            ));
        } catch (PaymentProviderRejected $e) {
            if (! $e->getPrevious() instanceof InvalidRequestException) {
                throw $e;
            }

            // Stripe refused to expire the session. Its status, not the error text, says why.
            $session = $this->retrieve($payment);

            if ($session->status === 'open') {
                throw $e;
            }

            return $this->outcome($session);
        }

        return new Cancelled;
    }

    /**
     * @throws UnexpectedValueException when the secret is missing or the payload is malformed
     * @throws SignatureVerificationException when the signature does not match
     */
    public static function verifyWebhook(string $payload, string $signature, ?string $secret): Event
    {
        if ($secret === null || $secret === '') {
            throw new UnexpectedValueException('The Stripe webhook secret is not configured.');
        }

        return Webhook::constructEvent($payload, $signature, $secret);
    }

    /**
     * @return array<string, mixed>
     */
    private function lineItem(string $name, Money $unitPrice, int $quantity): array
    {
        return [
            'price_data' => [
                'currency' => 'usd',
                'unit_amount' => $unitPrice->cents(),
                'product_data' => ['name' => $name],
            ],
            'quantity' => $quantity,
        ];
    }

    private function outcome(Session $session): PaymentOutcome
    {
        if ($session->status === 'expired') {
            return new Cancelled;
        }

        if ($session->status !== 'complete' || $session->payment_status !== 'paid') {
            return new StillPending;
        }

        $intent = $session->payment_intent;
        $card = is_object($intent) ? ($intent->latest_charge->payment_method_details->card ?? null) : null;

        return new Succeeded(
            is_object($intent) ? (string) $intent->id : (string) $intent,
            Money::fromCents((int) $session->amount_total),
            (string) $session->currency,
            $card?->brand,
            $card?->last4,
        );
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function call(Payment $payment, Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ApiConnectionException $e) {
            throw new PaymentProviderUnavailable("Stripe could not be reached for payment {$payment->id}.", 0, $e);
        } catch (ApiErrorException $e) {
            $detail = "payment {$payment->id}, HTTP ".($e->getHttpStatus() ?? 'unknown').', code '.($e->getStripeCode() ?? 'none');

            if (($e->getHttpStatus() ?? 0) >= 500) {
                throw new PaymentProviderUnavailable("Stripe failed for {$detail}.", 0, $e);
            }

            throw new PaymentProviderRejected("Stripe rejected the request for {$detail}.", 0, $e);
        }
    }
}
