<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;

/**
 * After a refund, the customer may no longer review what that order held,
 * unless another placed order of theirs holds the same product.
 */
class RemoveReviewsForRefundedOrder
{
    /**
     * @return int How many reviews were deleted.
     */
    public function handle(Order $order): int
    {
        $deleted = 0;

        $products = Product::query()
            ->whereIn('id', $order->items()->whereNotNull('product_id')->select('product_id'))
            ->get();

        foreach ($products as $product) {
            if (! Review::canReview($order->user, $product)) {
                $deleted += Review::query()
                    ->whereBelongsTo($order->user)
                    ->whereBelongsTo($product)
                    ->delete();
            }
        }

        return $deleted;
    }
}
