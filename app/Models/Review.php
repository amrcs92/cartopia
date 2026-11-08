<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A buyer's stars for a product, with optional text. One per customer and
 * product; reviewing again replaces it.
 *
 * @property int $id
 * @property int $user_id
 * @property int $product_id
 * @property int $stars
 * @property string|null $body
 * @property Carbon|null $hidden_at Set when an admin hides the review; it then leaves the list and the stats.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'product_id', 'stars', 'body'])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    /** How long after posting a change still isn't shown as "Edited". */
    private const int EDIT_GRACE_SECONDS = 60;

    /**
     * Whether the customer has a placed order holding the product: the one
     * rule for who may review.
     */
    public static function canReview(User $user, Product $product): bool
    {
        return DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.user_id', $user->id)
            ->where('orders.status', OrderStatus::Placed->value)
            ->where('order_items.product_id', $product->id)
            ->exists();
    }

    /**
     * Deletes every review whose author no longer passes canReview().
     *
     * @return int How many were deleted.
     */
    public static function deleteIneligible(): int
    {
        return self::query()
            ->whereNotExists(fn (QueryBuilder $query) => $query->select(DB::raw(1))
                ->from('orders')
                ->join('order_items', 'order_items.order_id', '=', 'orders.id')
                ->whereColumn('orders.user_id', 'reviews.user_id')
                ->whereColumn('order_items.product_id', 'reviews.product_id')
                ->where('orders.status', OrderStatus::Placed->value))
            ->delete();
    }

    public function isEdited(): bool
    {
        return $this->created_at !== null
            && $this->updated_at !== null
            && $this->updated_at->greaterThan($this->created_at->copy()->addSeconds(self::EDIT_GRACE_SECONDS));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['stars' => 'integer', 'hidden_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @param  Builder<Review>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->whereNull('reviews.hidden_at');
    }
}
