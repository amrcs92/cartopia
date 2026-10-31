<?php

namespace App\Http\Controllers;

use App\Cart\Cart;
use App\Cart\CartLine;
use App\Catalog\FeaturedProducts;
use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CartItemController extends Controller
{
    private const SUGGESTIONS = 2;

    public function store(AddToCartRequest $request, Cart $cart): RedirectResponse
    {
        $product = Product::findOrFail($request->integer('product_id'));
        $quantity = $request->integer('quantity');

        $cart->add($product, $quantity);

        // Inertia's native flash: delivered once, never kept in history state.
        Inertia::flash('addedToBasket', $this->addedToBasket($cart, $product, $quantity));

        return redirect()->back();
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

    /**
     * @return array<string, mixed>
     */
    private function addedToBasket(Cart $cart, Product $product, int $quantity): array
    {
        $summary = $cart->summary();
        $inBasket = array_map(fn (CartLine $line): int => $line->product->id, $summary->lines);

        $suggestions = FeaturedProducts::take(self::SUGGESTIONS + count($inBasket))
            ->reject(fn (Product $featured): bool => in_array($featured->id, $inBasket, true))
            ->take(self::SUGGESTIONS)
            ->values();

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'image_url' => (new ProductResource($product->loadMissing('primaryImage')))->resolve()['image_url'],
            ],
            'quantity' => $quantity,
            'line_total' => $product->price->multiply($quantity)->format(),
            'basket' => [
                'count' => $summary->totalQuantity(),
                'subtotal' => $summary->subtotal->format(),
            ],
            'suggestions' => ProductResource::collection($suggestions)->resolve(),
        ];
    }
}
