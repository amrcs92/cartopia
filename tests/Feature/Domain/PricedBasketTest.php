<?php

use App\Cart\Cart;
use App\Cart\PricedBasket;
use App\Exceptions\EmptyCart;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;

it('prices the basket with the delivery fee', function () {
    [$user, $ups] = checkoutSetup();

    $basket = PricedBasket::of(Cart::forUser($user), $ups);

    expect($basket->subtotal()->cents())->toBe(774)
        ->and($basket->fee->cents())->toBe(500)
        ->and($basket->total->cents())->toBe(1274)
        ->and($basket->summary->lines)->toHaveCount(2);
});

it('snapshots each line as priced now', function () {
    $user = User::factory()->create();
    $peas = Product::factory()->create(['name' => 'Garden Peas', 'price' => 250]);
    Cart::forUser($user)->add($peas, 3);

    $items = PricedBasket::of(Cart::forUser($user), ShippingMethod::factory()->pickup()->create())->itemAttributes();

    expect($items)->toHaveCount(1)
        ->and($items[0]['product_id'])->toBe($peas->id)
        ->and($items[0]['product_name'])->toBe('Garden Peas')
        ->and($items[0]['unit_price']->cents())->toBe(250)
        ->and($items[0]['quantity'])->toBe(3)
        ->and($items[0]['line_total']->cents())->toBe(750);
});

it('refuses an empty basket', function () {
    PricedBasket::of(Cart::forUser(User::factory()->create()), ShippingMethod::factory()->ups()->create());
})->throws(EmptyCart::class, 'Your basket is empty.');
