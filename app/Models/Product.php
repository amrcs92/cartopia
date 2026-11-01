<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $category_id
 * @property bool $is_featured
 * @property string $name
 * @property string|null $short_name A shorter name for tight spots, such as the home page's price board
 * @property string $slug
 * @property string $description
 * @property Money $price
 * @property string $sku
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['category_id', 'is_featured', 'name', 'short_name', 'slug', 'description', 'price', 'sku'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    public const array SORTS = ['newest', 'price_asc', 'price_desc', 'rating'];

    private const string AVG_STARS_SQL = '(select avg(stars) from ratings where ratings.product_id = products.id)';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['price' => MoneyCast::class, 'is_featured' => 'bool'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * @return HasOne<ProductImage, $this>
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->oldestOfMany('sort_order');
    }

    /**
     * @return HasMany<Rating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        if ($term === null || trim($term) === '') {
            return;
        }

        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        $query->where(function (Builder $q) use ($like): void {
            $q->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("description LIKE ? ESCAPE '!'", [$like]);
        });
    }

    /**
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function priceBetween(Builder $query, ?int $minCents, ?int $maxCents): void
    {
        if ($minCents !== null) {
            $query->where('price', '>=', $minCents);
        }

        if ($maxCents !== null) {
            $query->where('price', '<=', $maxCents);
        }
    }

    /**
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function minRating(Builder $query, ?int $stars): void
    {
        if ($stars === null) {
            return;
        }

        $query->whereRaw(self::AVG_STARS_SQL.' >= ?', [$stars]);
    }

    /**
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function sortBy(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price')->orderByDesc('id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('id'),
            'rating' => $query->orderByRaw(self::AVG_STARS_SQL.' desc')->orderByDesc('id'),
            default => $query->latest('id'),
        };
    }

    /**
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function withRatingStats(Builder $query): void
    {
        $query->withAvg('ratings', 'stars')->withCount('ratings');
    }

    /**
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function featured(Builder $query): void
    {
        $query->where('is_featured', true)->latest('id');
    }

    /**
     * @param  Builder<Product>  $query
     * @param  array<int, string>|null  $slugs
     */
    #[Scope]
    protected function inAisles(Builder $query, ?array $slugs): void
    {
        if ($slugs === null || $slugs === []) {
            return;
        }

        $query->whereHas('category', fn (Builder $q) => $q->whereIn('slug', $slugs));
    }

    /**
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function rated(Builder $query): void
    {
        $query->whereHas('ratings');
    }
}
