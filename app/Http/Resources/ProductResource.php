<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Expects the product loaded with `primaryImage` and `withRatingStats()`.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $image = $this->primaryImage;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'slug' => $this->slug,
            'price' => $this->price->format(),
            'image_url' => $image === null ? null : Storage::disk('public')->url($image->path),
            'rating_average' => round((float) ($this->ratings_avg_stars ?? 0), 1),
            'rating_count' => (int) ($this->ratings_count ?? 0),
            'aisle' => $this->whenLoaded('category', fn (): ?string => $this->category?->name),
        ];
    }
}
