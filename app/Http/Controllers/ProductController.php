<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductIndexRequest;
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
            ->with('primaryImage')
            ->withRatingStats()
            ->search($filters['q'])
            ->priceBetween($filters['min_price'], $filters['max_price'])
            ->minRating($filters['rating'])
            ->sortBy($filters['sort'])
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('products/ProductsIndexPage', [
            'products' => ProductResource::collection($products),
            'filters' => $request->rawFilters(),
        ]);
    }

    public function show(Product $product): Response
    {
        $product->load(['images', 'primaryImage'])
            ->loadAvg('ratings', 'stars')
            ->loadCount('ratings');

        return Inertia::render('products/ProductShowPage', [
            'product' => (new ProductDetailResource($product))->resolve(),
        ]);
    }
}
