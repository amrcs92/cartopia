<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public const int FEATURED_COUNT = 4;

    public function __invoke(): Response
    {
        $featured = Product::query()
            ->with('primaryImage')
            ->withRatingStats()
            ->sortBy('newest')
            ->limit(self::FEATURED_COUNT)
            ->get();

        return Inertia::render('WelcomePage', [
            'featured' => ProductResource::collection($featured)->resolve(),
        ]);
    }
}
