<?php

namespace App\Http\Controllers;

use App\Actions\PlaceOrder;
use App\Exceptions\EmptyCart;
use App\Exceptions\InsufficientBalance;
use App\Http\Requests\CheckoutRequest;
use App\Models\ShippingMethod;
use Illuminate\Http\RedirectResponse;

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

        return to_route('orders.show', $order)->with('success', 'Order placed.');
    }
}
