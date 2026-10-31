<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Expects the order loaded with `shippingMethod` and `items.product.primaryImage`.
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
            'thumbnails' => $this->thumbnails(),
            'items' => OrderItemResource::collection($this->items)->resolve($request),
        ];
    }

    /**
     * Photos of the first two lines, skipping products without one.
     *
     * @return list<string>
     */
    private function thumbnails(): array
    {
        $urls = $this->items
            ->map(fn ($item) => $item->product?->primaryImage?->path)
            ->filter()
            ->take(2)
            ->map(fn (string $path) => Storage::disk('public')->url($path))
            ->all();

        return array_values($urls);
    }
}
