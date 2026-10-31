<?php

use App\Enums\WalletTransactionType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\Money;

it('keeps the orders, receipt and wallet pages from scrolling sideways at 360px', function () {
    $user = User::factory()->withBalance(Money::fromCents(10000))->create();
    $order = Order::factory()->for($user)->create();
    OrderItem::factory()->for($order)->create(['product_id' => Product::factory()->withImages()->create()->id]);
    OrderItem::factory()->for($order)->create(['product_id' => Product::factory()->create()->id]);

    foreach (range(1, 6) as $i) {
        WalletTransaction::create([
            'user_id' => $user->id,
            'type' => WalletTransactionType::AdminAdjustment,
            'amount' => Money::fromCents(123456),
            'balance_after' => Money::fromCents(1234567),
            'description' => 'A very long description of a goodwill credit number '.$i.' added by the shop team',
        ]);
    }

    $this->actingAs($user);

    foreach (['/orders', "/orders/{$order->id}", '/wallet'] as $path) {
        visit($path)->resize(360, 800)
            ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
            ->assertNoJavaScriptErrors();
    }

    // The history table scrolls inside its own box, not the page.
    visit('/wallet')->resize(360, 800)
        ->assertPresent('[data-test="ledger-row"]')
        ->assertScript('(() => { const box = document.querySelector("[role=region][aria-label=\'Transaction history\']"); return box.scrollWidth > box.clientWidth; })()', true)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
});

it('leaves the order-placed banner off a plain visit to the receipt', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();

    $this->actingAs($user);

    visit("/orders/{$order->id}")
        ->assertDontSee('Order placed.')
        ->assertNoJavaScriptErrors();
});

it('shows the order-placed banner after checkout but not when coming back to it', function () {
    $user = User::factory()->withBalance(Money::fromCents(10000))->create();
    Product::factory()->create(['name' => 'Apple', 'price' => 300]);
    ShippingMethod::factory()->pickup()->create();

    $this->actingAs($user);

    visit('/products')->click('[aria-label="Add Apple to basket"]')
        ->waitForText('Added to your basket')
        ->click('View basket and check out')
        ->waitForText('How do you want it?')
        ->click('[data-test="checkout-button"]')
        ->waitForText('Your receipt is below.')
        ->assertSee('Order placed.')
        ->click('a:has-text("Back to orders")')
        ->waitForText('Your orders')
        ->back()
        ->waitForText('Wallet balance afterwards')
        ->wait(0.8)
        ->assertDontSee('Order placed.')
        ->assertDontSee('Your receipt is below.');
});
