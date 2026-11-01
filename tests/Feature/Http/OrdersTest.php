<?php

use App\Enums\PaymentMethod;
use App\Enums\WalletTransactionType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

it('hides orders that are not placed', function () {
    $user = User::factory()->create();
    $placed = Order::factory()->for($user)->create();
    $awaiting = Order::factory()->for($user)->awaitingPayment()->create();
    Order::factory()->for($user)->cancelled()->create();

    $this->actingAs($user)
        ->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $placed->id));

    $this->get(route('orders.show', $awaiting))->assertNotFound();
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

it('answers 404 for another users order', function () {
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('orders.show', $order))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('errors/NotFoundPage'));
});

it('redirects guests to login', function () {
    $order = Order::factory()->create();

    $this->get(route('orders.index'))->assertRedirect(route('login'));
    $this->get(route('orders.show', $order))->assertRedirect(route('login'));
});

it('gives each order at most two product thumbnails, skipping products without a photo', function () {
    $user = User::factory()->create();

    $withPhotos = Order::factory()->for($user)->create(['created_at' => now()->subDay()]);
    $first = Product::factory()->withImages()->create();
    $second = Product::factory()->withImages()->create();
    $third = Product::factory()->withImages()->create();
    foreach ([$first, $second, $third] as $product) {
        OrderItem::factory()->for($withPhotos)->create(['product_id' => $product->id]);
    }

    $noPhoto = Order::factory()->for($user)->create(['created_at' => now()->subDays(2)]);
    OrderItem::factory()->for($noPhoto)->create(['product_id' => Product::factory()->create()->id]);

    $this->actingAs($user)
        ->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data.0.thumbnails', 2)
            ->where('orders.data.0.thumbnails.0', Storage::disk('public')->url($first->images()->first()->path))
            ->where('orders.data.0.thumbnails.1', Storage::disk('public')->url($second->images()->first()->path))
            ->where('orders.data.1.thumbnails', []));
});

it('does not query once per order for thumbnails', function () {
    $user = User::factory()->create();

    $countQueries = function () use ($user): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('orders.index'))->assertOk();

        return count(DB::getQueryLog());
    };

    $addOrders = function (int $orders) use ($user): void {
        foreach (range(1, $orders) as $i) {
            $order = Order::factory()->for($user)->create();
            foreach (range(1, 2 + $i % 2) as $j) {
                OrderItem::factory()->for($order)->create(['product_id' => Product::factory()->withImages()->create()->id]);
            }
        }
    };

    $addOrders(2);
    $withTwo = $countQueries();

    $addOrders(4);
    $withSix = $countQueries();

    expect($withSix)->toBe($withTwo);
});

it('shows the wallet balance after the order on the receipt', function () {
    $user = User::factory()->withBalance(Money::fromCents(10000))->create();
    $this->actingAs($user);
    $order = Order::factory()->for($user)->create();
    $order->walletTransactions()->create([
        'user_id' => $user->id,
        'type' => WalletTransactionType::Purchase,
        'amount' => Money::fromCents(-1274),
        'balance_after' => Money::fromCents(8726),
    ]);

    $this->get(route('orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('balance_after', '$87.26'));

    $bare = Order::factory()->for($user)->create();
    $this->get(route('orders.show', $bare))
        ->assertInertia(fn (Assert $page) => $page->where('balance_after', null));
});

it('says how a wallet order was paid on the receipt', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.payment.method', 'wallet')
            ->where('order.payment.paid_with', 'Cartopia wallet')
            ->where('order.payment.reference', null)
            ->where('order.payment.tag', 'Paid with wallet'));
});

it('says how a card order was paid on the receipt and in the list', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['payment_method' => PaymentMethod::Stripe, 'wallet_amount' => 0]);
    Payment::factory()->forOrder($order)->stripe()->succeeded()->create([
        'provider_payment_id' => 'pi_3Q8xKLMNOP7Kd2',
        'card_brand' => 'visa',
        'card_last4' => '4242',
    ]);

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.payment.tag', 'Paid with Stripe')
            ->where('order.payment.paid_with', 'Visa ending in 4242, through Stripe')
            ->where('order.payment.reference.short', 'Stripe payment pi_3Q8xK…7Kd2')
            ->where('order.payment.slip_lines.0.label', 'Paid through Stripe'));

    $this->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page->where('orders.data.0.payment.tag', 'Paid with Stripe'));
});
