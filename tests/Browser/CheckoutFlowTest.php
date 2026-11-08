<?php

use App\Cart\Cart;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Support\Money;

it('browses, carts, logs in, checks out and sees the order', function () {
    User::factory()->withBalance(Money::fromCents(10000))->create([
        'email' => 'shopper@example.com',
    ]);
    Product::factory()->withImages()->create(['name' => 'Garden Peas', 'price' => 1000]);
    ShippingMethod::factory()->pickup()->create();
    ShippingMethod::factory()->ups()->create();

    // The product card's basket button is icon-only, so it is found by its
    // accessible name.
    visit('/products')
        ->click('[aria-label="Add Garden Peas to basket"]')
        ->waitForText('Added to your basket')
        ->click('View basket and check out')
        ->waitForText('Your basket')
        ->click('Log in to check out')
        ->waitForText('Log in to your account')
        ->fill('email', 'shopper@example.com')
        ->fill('password', 'password')
        ->press('Log in')
        ->waitForText('How do you want it?')
        ->click('UPS')
        ->click('Place order and pay $15.00')
        ->waitForText('Order placed.')
        ->assertSee('Order placed.')
        ->assertSee('$15.00')
        ->assertNoJavaScriptErrors();
});

it('shows a hidden product as no longer available and only checks out once it is removed', function () {
    $user = User::factory()->withBalance(Money::fromCents(10000))->create();
    $shown = Product::factory()->create(['name' => 'Garden Peas', 'price' => 1000]);
    $gone = Product::factory()->create(['name' => 'Retired Mug', 'price' => 900]);
    ShippingMethod::factory()->ups()->create();
    Cart::forUser($user)->add($shown, 1);
    Cart::forUser($user)->add($gone, 2);
    $gone->update(['is_visible' => false]);

    $this->actingAs($user);

    visit('/cart')
        ->waitForText('Your basket')
        ->assertSee('No longer available')
        ->assertSeeIn('[data-test="cart-subtotal"]', '$10.00')
        ->assertSeeIn('[data-test="cart-total-quantity"]', '1')
        ->click('UPS')
        ->click('Place order and pay $15.00')
        ->waitForText('Remove Retired Mug from your basket to continue.')
        ->click('[aria-label="Remove Retired Mug from basket"]')
        ->wait(1)
        ->assertDontSee('No longer available')
        ->click('Place order and pay $15.00')
        ->waitForText('Order placed.')
        ->assertNoJavaScriptErrors();
});
