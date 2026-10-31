<?php

namespace App\Http\Controllers;

use App\Cart\Cart;
use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;

class CartItemController extends Controller
{
    public function store(AddToCartRequest $request, Cart $cart): RedirectResponse
    {
        $cart->add(Product::findOrFail($request->integer('product_id')), $request->integer('quantity'));

        return redirect()->back()->with('success', 'Added to cart.');
    }

    public function update(UpdateCartItemRequest $request, Cart $cart, Product $product): RedirectResponse
    {
        abort_unless($cart->has($product), 404);

        $cart->update($product, $request->integer('quantity'));

        return redirect()->back();
    }

    public function destroy(Cart $cart, Product $product): RedirectResponse
    {
        abort_unless($cart->has($product), 404);

        $cart->remove($product);

        return redirect()->back();
    }
}
