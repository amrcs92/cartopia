<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Expects the product loaded with `images`, `primaryImage` and rating stats.
 *
 * @mixin Product
 */
class ProductDetailResource extends ProductResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'category' => $this->category === null
                ? null
                : ['name' => $this->category->name, 'slug' => $this->category->slug],
            'images' => $this->images
                ->map(fn (ProductImage $image): string => Storage::disk('public')->url($image->path))
                ->values()
                ->all(),
            'my_rating' => $user === null
                ? null
                : $this->ratings()->whereBelongsTo($user)->value('stars'),
        ];
    }
}
