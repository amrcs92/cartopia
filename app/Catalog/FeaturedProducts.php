<?php

namespace App\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class FeaturedProducts
{
    /**
     * The newest featured products, optionally leaving one out.
     *
     * @return Collection<int, Product>
     */
    public static function take(int $limit = 8, ?int $exceptId = null): Collection
    {
        return Product::query()
            ->visible()
            ->featured()
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->with(['primaryImage', 'category'])
            ->withRatingStats()
            ->limit($limit)
            ->get();
    }
}
