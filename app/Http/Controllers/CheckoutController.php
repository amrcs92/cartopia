<?php

namespace App\Http\Controllers;

use App\Actions\PlaceOrder;
use App\Exceptions\EmptyCart;
use App\Exceptions\InsufficientBalance;
use App\Http\Requests\CheckoutRequest;
use App\Models\ShippingMethod;
use App\Payments\PaymentWording;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function store(CheckoutRequest $request, PlaceOrder $placeOrder): RedirectResponse
    {
        $method = ShippingMethod::query()->findOrFail($request->integer('shipping_method_id'));

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
