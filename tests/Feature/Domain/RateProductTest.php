<?php

use App\Actions\RateProduct;
use App\Models\Product;
use App\Models\Rating;
use App\Models\User;

it('creates then updates a single rating', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    app(RateProduct::class)->handle($user, $product, 3);
    app(RateProduct::class)->handle($user, $product, 5);

    expect(Rating::count())->toBe(1)
        ->and(Rating::first()->stars)->toBe(5);
});
