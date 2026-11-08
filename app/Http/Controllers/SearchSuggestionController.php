<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The header search's live results: up to six visible products matching what
 * has been typed so far, by name first, as plain JSON for the dropdown.
 */
class SearchSuggestionController extends Controller
{
    public const int LIMIT = 6;

    public const int MIN_LENGTH = 2;

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim(mb_substr((string) $request->query('q', ''), 0, 100));

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return response()->json(['query' => $term, 'total' => 0, 'products' => []]);
        }

        $matches = Product::query()->visible()->search($term);
        $nameLike = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        $products = (clone $matches)
            ->with('primaryImage')
            ->orderByRaw("CASE WHEN name LIKE ? ESCAPE '!' THEN 0 ELSE 1 END", [$nameLike])
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get();

        return response()->json([
            'query' => $term,
            'total' => $matches->count(),
            'products' => $products->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price->format(),
                'image_url' => $product->primaryImage === null ? null : Storage::disk('public')->url($product->primaryImage->path),
                'url' => route('products.show', $product),
            ])->all(),
        ]);
    }
}
