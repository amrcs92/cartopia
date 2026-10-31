<?php

namespace App\Http\Controllers;

use App\Cart\Cart;
use App\Http\Resources\CartResource;
use App\Http\Resources\ShippingMethodResource;
use App\Models\ShippingMethod;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function show(Cart $cart): Response
    {
        return Inertia::render('cart/CartPage', [
            'cart' => (new CartResource($cart->summary()))->resolve(),
            'shippingMethods' => ShippingMethodResource::collection(ShippingMethod::query()->orderBy('id')->get())->resolve(),
        ]);
    }
}
