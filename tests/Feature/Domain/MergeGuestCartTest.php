<?php

use App\Models\Cart as CartModel;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Events\Login;

it('merges guest lines into the user cart on login', function () {
    $user = User::factory()->create();
    $apple = Product::factory()->create();
    $beer = Product::factory()->create();
    $cart = CartModel::factory()->for($user)->create();
    CartItem::factory()->for($cart)->for($beer)->create(['quantity' => 5]);
    session(['cart' => [$apple->id => 3, $beer->id => 98]]);

    event(new Login('web', $user, false));

    expect($cart->items()->pluck('quantity', 'product_id')->all())
        ->toEqual([$beer->id => 99, $apple->id => 3])
        ->and(session('cart'))->toBeEmpty();
});

it('creates a database cart when the user had none', function () {
    $user = User::factory()->create();
    $apple = Product::factory()->create();
    session(['cart' => [$apple->id => 2]]);

    event(new Login('web', $user, false));

    expect($user->cart->items()->pluck('quantity', 'product_id')->all())->toBe([$apple->id => 2]);
});
