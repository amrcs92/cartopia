<?php

use App\Actions\RateProduct;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;

it('creates then updates a single rating', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    app(RateProduct::class)->handle($user, $product, 3);
    app(RateProduct::class)->handle($user, $product, 5);

    expect(Review::count())->toBe(1)
        ->and(Review::first()->stars)->toBe(5);
});
