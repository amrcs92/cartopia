<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;

/**
 * One review per customer and product; saving again replaces the stars and
 * text. A review an admin hid stays hidden.
 */
class SaveReview
{
    public function handle(User $user, Product $product, int $stars, ?string $body): Review
    {
        return Review::updateOrCreate(
            ['user_id' => $user->id, 'product_id' => $product->id],
            ['stars' => $stars, 'body' => $body],
        );
    }
}
