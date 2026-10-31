<?php

use App\Cart\Cart;
use App\Cart\DatabaseCartStore;
use App\Cart\SessionCartStore;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;

dataset('stores', [
    'session' => fn () => new SessionCartStore(app('session.store')),
    'database' => fn () => new DatabaseCartStore(User::factory()->create()),
]);

it('adds and accumulates quantity', function ($store) {
    $cart = new Cart($store);
    $apple = Product::factory()->create();

    $cart->add($apple, 2);
    $cart->add($apple, 3);

    expect($cart->summary()->lines[0]->quantity)->toBe(5)
        ->and($cart->has($apple))->toBeTrue();
})->with('stores');

it('caps quantity at 99', function ($store) {
    $cart = new Cart($store);
    $apple = Product::factory()->create();

    $cart->add($apple, 98);
    $cart->add($apple, 5);
    expect($cart->count())->toBe(99);

    $cart->update($apple, 150);
    expect($cart->count())->toBe(99);
})->with('stores');

it('rejects updating to less than one', function ($store) {
    $cart = new Cart($store);
    $apple = Product::factory()->create();

    $cart->update($apple, 0);
})->with('stores')->throws(InvalidArgumentException::class);

it('removes and clears lines', function ($store) {
    $cart = new Cart($store);
    $apple = Product::factory()->create();
    $beer = Product::factory()->create();
    $cart->add($apple, 1);
    $cart->add($beer, 1);

    $cart->remove($apple);
    expect($cart->has($apple))->toBeFalse()
        ->and($cart->has($beer))->toBeTrue();

    $cart->clear();
    expect($cart->summary()->isEmpty())->toBeTrue()
        ->and($cart->count())->toBe(0);
})->with('stores');

it('prices lines from the current database price', function ($store) {
    $cart = new Cart($store);
    $beer = Product::factory()->create(['price' => 200]);
    $cart->add($beer, 2);

    $beer->update(['price' => 250]);

    expect($cart->summary()->subtotal->format())->toBe('$5.00');
})->with('stores');

it('drops products that no longer exist', function ($store) {
    $cart = new Cart($store);
    $apple = Product::factory()->create();
    $beer = Product::factory()->create();
    $cart->add($apple, 4);
    $cart->add($beer, 2);

    $beer->delete();

    $summary = $cart->summary();
    expect($summary->lines)->toHaveCount(1)
        ->and($cart->count())->toBe(4)
        ->and($summary->totalQuantity())->toBe(4);
})->with('stores');

it('counts total quantity across lines', function ($store) {
    $cart = new Cart($store);
    $cart->add(Product::factory()->create(), 2);
    $cart->add(Product::factory()->create(), 3);

    expect($cart->count())->toBe(5)
        ->and($cart->summary()->totalQuantity())->toBe(5)
        ->and($cart->summary()->subtotal)->toBeInstanceOf(Money::class);
})->with('stores');
