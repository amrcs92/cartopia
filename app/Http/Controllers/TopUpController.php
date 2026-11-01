<?php

namespace App\Http\Controllers;

use App\Actions\StartTopUpPayment;
use App\Enums\PaymentMethod;
use App\Http\Requests\TopUpRequest;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class TopUpController extends Controller
{
    /**
     * Opens the payment and sends the browser to the provider's page. An
     * Inertia visit gets a 409 with the location, a plain request a redirect.
     */
    public function store(TopUpRequest $request, StartTopUpPayment $startTopUpPayment): Response
    {
        $method = $request->paymentMethod();

        try {
            $started = $startTopUpPayment->handle($request->user(), $request->amount(), $method);
        } catch (PaymentProviderUnavailable|PaymentProviderRejected $e) {
            // The message only: the previous exception can carry request headers.
            Log::warning("Couldn't start a {$method->value} top-up payment: {$e->getMessage()}");

            $provider = $method === PaymentMethod::Stripe ? 'Stripe' : 'PayPal';

            Inertia::flash('toast', ['type' => 'error', 'message' => "We couldn't reach {$provider}. Try again in a moment."]);

            return to_route('wallet.show');
        }

        return Inertia::location($started->redirectUrl);
    }
}
