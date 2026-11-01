<?php

namespace App\Http\Controllers;

use App\Actions\ClosePayment;
use App\Actions\SettlePayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\PayPalWebhookVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PayPalWebhookController extends Controller
{
    private const EVENTS = [
        'CHECKOUT.ORDER.APPROVED',
        'PAYMENT.CAPTURE.COMPLETED',
        'PAYMENT.CAPTURE.DENIED',
    ];

    public function __invoke(
        Request $request,
        PayPalWebhookVerifier $verifier,
        SettlePayment $settlePayment,
        ClosePayment $closePayment,
    ): Response {
        try {
            $verified = $verifier->verify($request);
        } catch (PaymentProviderUnavailable $e) {
            Log::warning('PayPal webhook could not be verified: '.$e->getMessage());

            return response('', 503);
        }

        if (! $verified) {
            Log::warning('PayPal webhook rejected: verification failed');

            return response('', 400);
        }

        $event = json_decode($request->getContent(), true);
        $type = is_array($event) ? ($event['event_type'] ?? null) : null;

        if (! is_string($type) || ! in_array($type, self::EVENTS, true)) {
            return response('', 200);
        }

        $payment = $this->findPayment($event['resource'] ?? null, $type);

        if (! $payment instanceof Payment) {
            return response('', 200);
        }

        try {
            if ($type === 'PAYMENT.CAPTURE.DENIED') {
                $closePayment->handle($payment, PaymentStatus::Failed, notify: true);
            } else {
                $settlePayment->handle($payment, notifyOnFailure: true);
            }
        } catch (PaymentProviderUnavailable $e) {
            Log::warning('PayPal webhook could not reach PayPal: '.$e->getMessage());

            return response('', 503);
        } catch (PaymentProviderRejected $e) {
            Log::error('PayPal webhook was rejected by PayPal: '.$e->getMessage());

            return response('', 500);
        }

        return response('', 200);
    }

    private function findPayment(mixed $resource, string $type): ?Payment
    {
        if (! is_array($resource)) {
            return null;
        }

        $customId = $resource['custom_id'] ?? null;

        if ((is_string($customId) || is_int($customId)) && ctype_digit((string) $customId)) {
            $payment = Payment::query()
                ->where('method', PaymentMethod::PayPal)
                ->whereKey((int) $customId)
                ->first();

            if ($payment instanceof Payment) {
                return $payment;
            }
        }

        $orderId = $type === 'CHECKOUT.ORDER.APPROVED'
            ? ($resource['id'] ?? null)
            : ($resource['supplementary_data']['related_ids']['order_id'] ?? null);

        if (! is_string($orderId) || $orderId === '') {
            return null;
        }

        return Payment::query()
            ->where('method', PaymentMethod::PayPal)
            ->where('provider_reference', $orderId)
            ->first();
    }
}
