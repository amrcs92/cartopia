<?php

namespace App\Http\Resources;

use App\Cart\CartLine;
use App\Cart\CartSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CartSummary
 */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Same key as the shared `cart.count` prop this page prop overrides,
            // so the layout badge keeps working on the cart page.
            'count' => $this->totalQuantity(),
            'lines' => array_map(fn (CartLine $line): array => [
                'product' => (new ProductResource($line->product))->resolve($request),
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice->format(),
                'line_total' => $line->lineTotal->format(),
            ], $this->lines),
            'subtotal' => $this->subtotal->format(),
            'subtotal_cents' => $this->subtotal->cents(),
            'total_quantity' => $this->totalQuantity(),
        ];
    }
}
