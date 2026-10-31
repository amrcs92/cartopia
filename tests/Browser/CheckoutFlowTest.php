<?php

use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Support\Money;

it('browses, carts, logs in, checks out and sees the order', function () {
    User::factory()->withBalance(Money::fromCents(10000))->create([
        'email' => 'shopper@example.com',
    ]);
    Product::factory()->withImages()->create(['price' => 1000]);
    ShippingMethod::factory()->pickup()->create();
    ShippingMethod::factory()->ups()->create();

    visit('/products')
        ->click('Add to cart')
        ->waitForText('Added to cart.')
        ->navigate('/cart')
        ->click('Log in to check out')
        ->waitForText('Log in to your account')
        ->fill('email', 'shopper@example.com')
        ->fill('password', 'password')
        ->press('Log in')
        ->waitForText('$100.00')
        ->navigate('/cart')
        ->click('#shipping-method')
        ->click('[role="option"]:has-text("UPS")')
        ->click('Check out')
        ->click('Place order')
        ->waitForText('Order placed.')
        ->assertSee('Order placed.')
        ->assertSee('$15.00')
        ->assertNoJavaScriptErrors();
});
