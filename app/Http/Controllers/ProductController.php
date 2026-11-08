<?php

namespace App\Http\Controllers;

use App\Catalog\Aisles;
use App\Catalog\FeaturedProducts;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Resources\AisleResource;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public const int PER_PAGE = 12;

    public const int REVIEWS_PER_PAGE = 5;

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

    public function show(Request $request, Product $product): Response
    {
        abort_unless($product->is_visible, 404);

        $visible = fn ($query) => $query->whereNull('hidden_at');

        $product->load(['images', 'primaryImage', 'category'])
            ->loadAvg(['reviews as ratings_avg_stars' => $visible], 'stars')
            ->loadCount(['reviews as ratings_count' => $visible]);

        /** @var User|null $user */
        $user = $request->user();

        return Inertia::render('products/ProductShowPage', [
            'product' => (new ProductDetailResource($product))->resolve(),
            'featured' => ProductResource::collection(FeaturedProducts::take(8, $product->id))->resolve(),
            'reviews' => fn (): array => $this->reviewState($product, $user),
            // Written reviews, newest first, five at a time; "Show more
            // reviews" asks for the next page and the client appends it.
            'reviewList' => Inertia::scroll(fn () => ReviewResource::collection(
                $product->reviews()
                    ->visible()
                    ->whereNotNull('body')
                    // The viewer's own review shows above the list instead.
                    ->when($user !== null, fn ($query) => $query->where('user_id', '!=', $user?->id))
                    ->with('user')
                    ->latest()
                    ->latest('id')
                    ->simplePaginate(self::REVIEWS_PER_PAGE, pageName: 'reviews_page'),
            )),
        ]);
    }

    /**
     * Whether the viewer may review the product ('guest', 'not_buyer' or
     * 'yes') and their own review, hidden or not.
     *
     * @return array{can_review: string, mine: array<string, mixed>|null}
     */
    private function reviewState(Product $product, ?User $user): array
    {
        if ($user === null) {
            return ['can_review' => 'guest', 'mine' => null];
        }

        $mine = Review::query()->whereBelongsTo($user)->whereBelongsTo($product)->with('user')->first();

        return [
            'can_review' => Review::canReview($user, $product) ? 'yes' : 'not_buyer',
            'mine' => $mine === null ? null : (new ReviewResource($mine))->resolve(),
        ];
    }
}
