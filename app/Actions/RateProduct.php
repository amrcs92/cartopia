<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;

/**
 * One rating per user and product; rating again replaces the stars.
 */
class RateProduct
{
    public function handle(User $user, Product $product, int $stars): Review
    {
        return Review::updateOrCreate(
            ['user_id' => $user->id, 'product_id' => $product->id],
            ['stars' => $stars],
        );
    }
}
