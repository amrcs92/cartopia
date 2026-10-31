<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('lists only the users own orders, newest first', function () {
    $user = User::factory()->create();
    $old = Order::factory()->for($user)->create(['created_at' => now()->subDays(2)]);
    $new = Order::factory()->for($user)->create(['created_at' => now()->subDay()]);
    Order::factory()->create();

    $this->actingAs($user)
        ->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('orders/OrdersIndexPage')
            ->has('orders.data', 2)
            ->where('orders.data.0.id', $new->id)
            ->where('orders.data.1.id', $old->id));
});

it('shows the users own order with items', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();
    OrderItem::factory()->for($order)->create(['product_name' => 'Beer', 'unit_price' => 200, 'quantity' => 2, 'line_total' => 400]);

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page
            ->component('orders/OrderShowPage')
            ->where('order.id', $order->id)
            ->where('order.status', 'placed')
            ->where('order.shipping_method', $order->shippingMethod->name)
            ->where('order.placed_at', $order->created_at->toIso8601String())
            ->has('order.items', 1)
            ->where('order.items.0.product_name', 'Beer')
            ->where('order.items.0.quantity', 2)
            ->where('order.items.0.unit_price', '$2.00')
            ->where('order.items.0.line_total', '$4.00')
            ->has('order.items.0.product_slug'));
});

it('forbids viewing another users order', function () {
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('orders.show', $order))
        ->assertForbidden();
});

it('redirects guests to login', function () {
    $order = Order::factory()->create();

    $this->get(route('orders.index'))->assertRedirect(route('login'));
    $this->get(route('orders.show', $order))->assertRedirect(route('login'));
});
