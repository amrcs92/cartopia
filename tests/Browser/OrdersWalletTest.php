<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;

it('keeps the orders, receipt and wallet pages from scrolling sideways at 360px', function () {
    $user = User::factory()->withBalance(Money::fromCents(10000))->create();
    $order = Order::factory()->for($user)->create();
    OrderItem::factory()->for($order)->create(['product_id' => Product::factory()->withImages()->create()->id]);
    OrderItem::factory()->for($order)->create(['product_id' => Product::factory()->create()->id]);

    $this->actingAs($user);

    foreach (['/orders', "/orders/{$order->id}", '/wallet'] as $path) {
        visit($path)->resize(360, 800)
            ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
            ->assertNoJavaScriptErrors();
    }
});

it('leaves the order-placed banner off a plain visit to the receipt', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();

    $this->actingAs($user);

    visit("/orders/{$order->id}")
        ->assertDontSee('Order placed.')
        ->assertNoJavaScriptErrors();
});
