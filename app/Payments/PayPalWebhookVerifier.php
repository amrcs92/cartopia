<?php

namespace App\Payments;

use App\Payments\Exceptions\PaymentProviderUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalWebhookVerifier
{
    public function __construct(private readonly PayPalGateway $gateway) {}

    /**
     * Asks PayPal whether the webhook's signature is genuine.
     *
     * @throws PaymentProviderUnavailable when PayPal can't answer, so the webhook can be retried
     */
    public function verify(Request $request): bool
    {
        $webhookId = (string) config('services.paypal.webhook_id');

        if ($webhookId === '') {
            Log::warning('PayPal webhook not verified: PAYPAL_WEBHOOK_ID (services.paypal.webhook_id) is not set.');

            return false;
        }

        $raw = $request->getContent();

        if ($raw === '' || ! json_validate($raw)) {
            return false;
        }

        // The event is spliced in as the raw bytes: decoding and re-encoding could change them.
        $fields = json_encode([
            'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
            'cert_url' => $request->header('PAYPAL-CERT-URL'),
            'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            'webhook_id' => $webhookId,
        ], JSON_THROW_ON_ERROR);

        $json = substr($fields, 0, -1).',"webhook_event":'.$raw.'}';

        try {
            $response = Http::withToken($this->gateway->accessToken())
                ->withBody($json, 'application/json')
                ->acceptJson()
                ->post(PayPalGateway::BASE_URL.'/v1/notifications/verify-webhook-signature');
        } catch (ConnectionException $e) {
            throw new PaymentProviderUnavailable('PayPal could not be reached to verify a webhook.', 0, $e);
        }

        if ($response->serverError()) {
            throw new PaymentProviderUnavailable("PayPal failed to verify a webhook, HTTP {$response->status()}.");
        }

        if (! $response->successful()) {
            Log::warning("PayPal refused to verify a webhook, HTTP {$response->status()}.");

            return false;
        }

        return $response->json('verification_status') === 'SUCCESS';
    }
}
