<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects the item loaded with `product`.
 *
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_name' => $this->product_name,
            'product_slug' => $this->product?->slug,
            'unit_price' => $this->unit_price->format(),
            'quantity' => $this->quantity,
            'line_total' => $this->line_total->format(),
        ];
    }
}
