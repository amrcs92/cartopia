<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects the category loaded with `products_count`.
 *
 * @mixin Category
 */
class AisleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'product_count' => (int) $this->products_count,
            'image_url' => $this->imageUrl(),
        ];
    }
}
