<?php

namespace App\Http\Controllers;

use App\Catalog\Aisles;
use App\Catalog\FeaturedProducts;
use App\Http\Resources\AisleResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ShippingMethodResource;
use App\Models\Product;
use App\Models\ShippingMethod;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public const int COUNTER_COUNT = 4;

    public function __invoke(): Response
    {
        $counter = Product::query()
            ->visible()
            ->with('primaryImage')
            ->withRatingStats()
            ->sortBy('newest')
            ->limit(self::COUNTER_COUNT)
            ->get();

        return Inertia::render('WelcomePage', [
            'counter' => ProductResource::collection($counter)->resolve(),
            'featured' => ProductResource::collection(FeaturedProducts::take())->resolve(),
            'aisles' => AisleResource::collection(Aisles::forHome())->resolve(),
            'shippingMethods' => ShippingMethodResource::collection(ShippingMethod::query()->orderBy('id')->get())->resolve(),
        ]);
    }
}
