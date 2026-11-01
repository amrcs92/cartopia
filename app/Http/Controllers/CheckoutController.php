<?php

namespace App\Http\Controllers;

use App\Actions\PlaceOrder;
use App\Actions\StartOrderPayment;
use App\Enums\PaymentMethod;
use App\Exceptions\EmptyCart;
use App\Exceptions\InsufficientBalance;
use App\Http\Requests\CheckoutRequest;
use App\Models\ShippingMethod;
use App\Payments\Exceptions\AmountTooSmall;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\PaymentWording;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends Controller
{
    public function store(CheckoutRequest $request, PlaceOrder $placeOrder, StartOrderPayment $startOrderPayment): Response
    {
        $method = ShippingMethod::query()->findOrFail($request->integer('shipping_method_id'));
        $paymentMethod = $request->paymentMethod();

        if ($paymentMethod->isHosted()) {
            return $this->startHostedPayment($request, $startOrderPayment, $method, $paymentMethod);
        }

        try {
            $order = $placeOrder->handle($request->user(), $method);
        } catch (InsufficientBalance) {
            return $this->refused('Your balance is too low for this order.');
        } catch (EmptyCart) {
            return $this->refused('Your basket is empty.');
        }

        // Inertia's native flash: delivered once, never kept in history state, so
        // the receipt banner does not come back when going Back.
        Inertia::flash('orderPlaced', PaymentWording::placedMessage($order));

        return to_route('orders.show', $order);
    }

    /**
     * Opens the payment and sends the browser to the provider's page. An
     * Inertia visit gets a 409 with the location, a plain request a redirect.
     */
    private function startHostedPayment(CheckoutRequest $request, StartOrderPayment $startOrderPayment, ShippingMethod $method, PaymentMethod $paymentMethod): Response
    {
        try {
            $started = $startOrderPayment->handle($request->user(), $method, $paymentMethod);
        } catch (EmptyCart) {
            return $this->refused('Your basket is empty.');
        } catch (AmountTooSmall) {
            return $this->refused('Card and PayPal payments start at $0.50.');
        } catch (PaymentProviderUnavailable|PaymentProviderRejected $e) {
            // The message only: the previous exception can carry request headers.
            Log::warning("Couldn't start a {$paymentMethod->value} checkout payment: {$e->getMessage()}");

            $provider = $paymentMethod === PaymentMethod::Stripe ? 'Stripe' : 'PayPal';

            return $this->refused("We couldn't reach {$provider}. Try again in a moment.");
        }

        return Inertia::location($started->redirectUrl);
    }

    /**
     * Back to the basket with a one-shot error: a toast, and the message for
     * the inline spot above the total (`checkoutError`).
     */
    private function refused(string $message): RedirectResponse
    {
        Inertia::flash([
            'toast' => ['type' => 'error', 'message' => $message],
            'checkoutError' => $message,
        ]);

        return to_route('cart.show');
    }
}
