<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects the order loaded with `shippingMethod` and `items.product`.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'subtotal' => $this->subtotal->format(),
            'shipping_fee' => $this->shipping_fee->format(),
            'total' => $this->total->format(),
            'shipping_method' => $this->shippingMethod->name,
            'placed_at' => $this->created_at->toIso8601String(),
            'items' => OrderItemResource::collection($this->items)->resolve($request),
        ];
    }
}
