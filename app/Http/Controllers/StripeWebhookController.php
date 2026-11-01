<?php

namespace App\Http\Controllers;

use App\Actions\ClosePayment;
use App\Actions\SettlePayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, SettlePayment $settlePayment, ClosePayment $closePayment): Response
    {
        try {
            $event = StripeGateway::verifyWebhook(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                config('services.stripe.webhook_secret'),
            );
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            Log::warning('Stripe webhook rejected: '.$e->getMessage());

            return response('', 400);
        }

        if (! in_array($event->type, ['checkout.session.completed', 'checkout.session.expired'], true)) {
            return response('', 200);
        }

        $payment = Payment::query()
            ->where('method', PaymentMethod::Stripe)
            ->where('provider_reference', $event->data->object->id)
            ->first();

        if (! $payment instanceof Payment) {
            return response('', 200);
        }

        try {
            if ($event->type === 'checkout.session.completed') {
                // Also reaches a payment closed meanwhile: Stripe is asked, read-only, whether it was paid.
                $settlePayment->handle($payment, notifyOnFailure: true, moneyMoved: true);
            } else {
                $closePayment->handle($payment, PaymentStatus::Expired, notify: true);
            }
        } catch (PaymentProviderUnavailable $e) {
            Log::warning('Stripe webhook could not reach Stripe: '.$e->getMessage());

            return response('', 503);
        } catch (PaymentProviderRejected $e) {
            Log::error('Stripe webhook was rejected by Stripe: '.$e->getMessage());

            return response('', 500);
        }

        return response('', 200);
    }
}
