<?php

namespace App\Http\Controllers;

use App\Actions\PlaceOrder;
use App\Exceptions\EmptyCart;
use App\Exceptions\InsufficientBalance;
use App\Http\Requests\CheckoutRequest;
use App\Models\ShippingMethod;
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
            return to_route('cart.show')->with('error', 'Your balance is too low for this order.');
        } catch (EmptyCart) {
            return to_route('cart.show')->with('error', 'Your cart is empty.');
        }

        // Inertia's native flash: delivered once, never kept in history state, so
        // the receipt banner does not come back when going Back.
        Inertia::flash('orderPlaced', 'Order placed.');

        return to_route('orders.show', $order);
    }
}
