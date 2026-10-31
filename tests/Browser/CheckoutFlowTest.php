<?php

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
        ->assertSeeIn('[data-test="cart-badge"]', '1')
        ->navigate('/cart')
        ->click('Log in to check out')
        ->waitForText('Log in to your account')
        ->fill('email', 'shopper@example.com')
        ->fill('password', 'password')
        ->press('Log in')
        ->waitForText('Shipping method')
        ->click('#shipping-method')
        ->click('[role="option"]:has-text("UPS")')
        ->click('Check out')
        ->click('Place order')
        ->waitForText('Order placed.')
        ->assertSee('Order placed.')
        ->assertSee('$15.00')
        ->assertNoJavaScriptErrors();
});
