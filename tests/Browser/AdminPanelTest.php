<?php

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\PaymentGateways;

it('gives an admin an Admin link in the account menu that opens the panel', function () {
    $this->actingAs(User::factory()->admin()->create());

    visit('/')->click('[aria-label="Account menu"]')
        ->assertSee('Admin')
        ->click('Admin')
        ->assertPathIs('/admin')
        ->assertNoJavaScriptErrors();
});

it('gives a shopper no Admin link', function () {
    $this->actingAs(User::factory()->create());

    visit('/')->click('[aria-label="Account menu"]')
        ->assertSee('Account settings')
        ->assertDontSee('Admin');
});

it('lets an admin credit a customer, who then sees the reason in their wallet', function () {
    $customer = User::factory()->create(['name' => 'Sam Shopper']);
    $this->actingAs(User::factory()->admin()->create());

    visit("/admin/customers/{$customer->id}")
        ->click('Adjust wallet')
        ->type('[id="mountedActionSchema0.amount"]', '5.00')
        ->type('[id="mountedActionSchema0.reason"]', 'Late delivery')
        ->click('Submit')
        ->assertSee('Wallet adjusted')
        ->click('Wallet')
        ->assertSee('Adjustment')
        ->assertSee('Late delivery')
        ->assertNoJavaScriptErrors();

    $this->actingAs($customer);

    visit('/wallet')->assertSee('Adjustment · Late delivery');
});

it('lets an admin refund a split order, and the shopper sees where the money went', function () {
    PaymentGateways::fake();
    $shopper = User::factory()->create();
    $order = Order::factory()->for($shopper)->create([
        'subtotal' => 12274, 'shipping_fee' => 0, 'total' => 12274,
        'payment_method' => PaymentMethod::Stripe, 'wallet_amount' => 10000,
    ]);
    Payment::factory()->forOrder($order)->succeeded()->create([
        'method' => PaymentMethod::Stripe, 'provider_payment_id' => 'pi_1', 'card_brand' => 'visa', 'card_last4' => '4242',
    ]);
    $this->actingAs(User::factory()->admin()->create());

    visit("/admin/orders/{$order->id}")
        ->click('Refund order')
        ->assertSee('Visa ending in 4242, through Stripe: $22.74')
        ->assertSee('Wallet: $100.00')
        ->type('[id="mountedActionSchema0.reason"]', 'Damaged in transit')
        ->click('Refund $122.74')
        ->assertSee("Order #{$order->id} is refunded.")
        ->assertNoJavaScriptErrors();

    $this->actingAs($shopper);

    visit("/orders/{$order->id}")
        ->assertSee('Refunded to Visa ending in 4242 · $22.74')
        ->assertSee('Refunded to your wallet · $100.00')
        ->assertNoJavaScriptErrors();
});
