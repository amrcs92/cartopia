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
