<?php

namespace App\Http\Controllers;

use App\Catalog\Aisles;
use App\Catalog\FeaturedProducts;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Resources\AisleResource;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public const int PER_PAGE = 12;

    public function index(ProductIndexRequest $request): Response
    {
        $filters = $request->filters();

        $products = Product::query()
            ->visible()
            ->with(['primaryImage', 'category'])
            ->withRatingStats()
            ->inAisles($filters['aisle'])
            ->when($filters['rated'], fn ($query) => $query->rated())
            ->when($filters['featured'], fn ($query) => $query->featured())
            ->search($filters['q'])
            ->priceBetween($filters['min_price'], $filters['max_price'])
            ->minRating($filters['rating'])
            ->reorder()
            ->sortBy($filters['sort'])
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('products/ProductsIndexPage', [
            'products' => new ProductCollection($products),
            'filters' => $request->rawFilters(),
            'aisles' => AisleResource::collection(Aisles::all())->resolve(),
            'featured' => ProductResource::collection(FeaturedProducts::take())->resolve(),
        ]);
    }

    public function show(Product $product): Response
    {
        abort_unless($product->is_visible, 404);

        $visible = fn ($query) => $query->whereNull('hidden_at');

        $product->load(['images', 'primaryImage', 'category'])
            ->loadAvg(['reviews as ratings_avg_stars' => $visible], 'stars')
            ->loadCount(['reviews as ratings_count' => $visible]);

        return Inertia::render('products/ProductShowPage', [
            'product' => (new ProductDetailResource($product))->resolve(),
            'featured' => ProductResource::collection(FeaturedProducts::take(8, $product->id))->resolve(),
        ]);
    }
}
