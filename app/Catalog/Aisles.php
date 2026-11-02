<?php

namespace App\Catalog;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class Aisles
{
    public const int HOME_LIMIT = 6;

    /**
     * The busiest aisles, for the home page.
     *
     * @return Collection<int, Category>
     */
    public static function forHome(): Collection
    {
        return Category::query()
            ->has('products', '>=', 1, 'and', fn ($query) => $query->visible())
            ->withCount(['products' => fn ($query) => $query->visible()])
            ->with('latestProductWithImage.primaryImage')
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->limit(self::HOME_LIMIT)
            ->get();
    }

    /**
     * Every aisle that has products, by name.
     *
     * @return Collection<int, Category>
     */
    public static function all(): Collection
    {
        return Category::query()
            ->has('products', '>=', 1, 'and', fn ($query) => $query->visible())
            ->withCount(['products' => fn ($query) => $query->visible()])
            ->with('latestProductWithImage.primaryImage')
            ->orderBy('name')
            ->get();
    }
}
