<?php

use App\Cart\Cart;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Inertia\Testing\AssertableInertia as Assert;

it('shares null user and zero cart for guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', null)
            ->where('cart.count', 0)
            ->where('flash', ['success' => null, 'error' => null])
        );
});

it('shares the formatted balance for users', function () {
    $user = User::factory()->withBalance(Money::fromCents(10000))->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'balance' => '$100.00',
                'is_admin' => false,
            ])
        );
});

it('shares the cart item count', function () {
    $user = User::factory()->create();
    Cart::forUser($user)->add(Product::factory()->create(), 2);
    Cart::forUser($user)->add(Product::factory()->create(), 3);

    $this->actingAs($user)
        ->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page->where('cart.count', 5));
});

it("counts a guest's session cart", function () {
    $product = Product::factory()->create();

    $this->withSession(['cart' => [$product->id => 4]])
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('cart.count', 4));
});

it('shares flash messages', function () {
    $this->withSession(['success' => 'Order placed.', 'error' => 'Your cart is empty.'])
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.success', 'Order placed.')
            ->where('flash.error', 'Your cart is empty.')
        );
});
