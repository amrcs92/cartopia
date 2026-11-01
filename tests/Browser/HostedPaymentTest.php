<?php

use App\Cart\Cart;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Payments\PaymentGateways;
use App\Support\Money;

function hostedShopper(int $balance = 10000): User
{
    $user = $balance > 0
        ? User::factory()->withBalance(Money::fromCents($balance))->create()
        : User::factory()->create();
    Product::factory()->withImages()->create(['name' => 'Garden Peas', 'price' => 1000]);
    ShippingMethod::factory()->pickup()->create();
    ShippingMethod::factory()->ups()->create();

    return $user;
}

/** The radios are visually hidden, so the browser clicks the card that wraps them. */
function payCard(string $method): string
{
    return "label:has([data-test=\"pay-{$method}\"])";
}

/** Adds Garden Peas to the basket through the product card, then opens the basket. */
function openBasketWithPeas(): mixed
{
    return visit('/products')
        ->click('[aria-label="Add Garden Peas to basket"]')
        ->waitForText('Added to your basket')
        ->click('View basket and check out')
        ->waitForText('How do you want it?');
}

it('pays with Stripe and shows the Stripe receipt', function () {
    PaymentGateways::fake();
    $user = hostedShopper();
    $this->actingAs($user);

    openBasketWithPeas()
        ->click('UPS')
        ->click(payCard('stripe'))
        ->press('Pay $15.00 with Stripe')
        // Wait on the receipt, which stays once rendered; the banner then comes with it.
        ->waitForText('Visa ending in 4242, through Stripe')
        ->assertSee('Your Stripe payment of $15.00 was approved.')
        ->assertNoJavaScriptErrors();

    expect(Payment::sole()->status)->toBe(PaymentStatus::Succeeded)
        ->and(Payment::sole()->order->status)->toBe(OrderStatus::Placed)
        ->and($user->refresh()->balance->format())->toBe('$100.00');
});

it('pays with PayPal', function () {
    PaymentGateways::fake();
    $user = hostedShopper();
    $this->actingAs($user);

    openBasketWithPeas()
        ->click('UPS')
        ->click(payCard('paypal'))
        ->press('Pay $15.00 with PayPal')
        ->waitForText('Paid through PayPal')
        ->assertSee('Your PayPal payment of $15.00 was approved.')
        ->assertNoJavaScriptErrors();

    expect(Payment::sole()->status)->toBe(PaymentStatus::Succeeded)
        ->and(Payment::sole()->order->status)->toBe(OrderStatus::Placed)
        ->and($user->refresh()->balance->format())->toBe('$100.00');
});

it('cancels and keeps the basket', function () {
    PaymentGateways::fake()->redirectToCancel();
    $user = hostedShopper();
    $this->actingAs($user);

    openBasketWithPeas()
        ->click('UPS')
        ->click(payCard('stripe'))
        ->press('Pay $15.00 with Stripe')
        // The message also shows in a toast that fades; the one under the total stays.
        ->waitForText('Payment cancelled. Nothing was charged.')
        ->assertSeeIn('[data-test="checkout-error"]', 'Payment cancelled. Nothing was charged.')
        ->assertSee('Garden Peas')
        ->assertNoJavaScriptErrors();

    expect(Payment::sole()->status)->toBe(PaymentStatus::Cancelled)
        ->and(Payment::sole()->order->status)->toBe(OrderStatus::Cancelled)
        ->and(Cart::forUser($user)->count())->toBe(1);
});

it('defaults to card when the wallet is short', function () {
    PaymentGateways::fake();
    $this->actingAs(hostedShopper(0));

    openBasketWithPeas()
        ->click('UPS')
        ->assertChecked('[data-test="pay-stripe"]')
        ->assertSee('Pay $15.00 with Stripe')
        ->assertNoJavaScriptErrors();
});

it('preselects no way to pay when the wallet is short and the total is below the minimum', function () {
    PaymentGateways::fake();
    $this->actingAs(User::factory()->create());
    Product::factory()->withImages()->create(['name' => 'Garden Peas', 'price' => 30]);
    ShippingMethod::factory()->pickup()->create();

    openBasketWithPeas()
        ->assertNotChecked('[data-test="pay-stripe"]')
        ->assertNotChecked('[data-test="pay-paypal"]')
        ->assertNotChecked('[data-test="pay-wallet"]')
        ->assertNoJavaScriptErrors();
});

it('splits a short wallet with Stripe', function () {
    PaymentGateways::fake();
    $user = hostedShopper(500);
    $this->actingAs($user);

    openBasketWithPeas()
        ->click('Pickup')
        ->assertSee('My $5.00 balance')
        ->assertSee('Wallet $5.00 · Stripe $5.00')
        ->press('Pay $5.00 with Stripe')
        // The toast lasts 4 seconds and can be gone by the time the page has loaded
        // on a busy machine, so wait for the receipt, which stays. The toast wording
        // is covered in PaymentWordingTest.
        ->waitForText('Paid through Stripe')
        ->assertSee('Paid from wallet')
        ->assertSee('Wallet balance after')
        ->assertSee('$0.00')
        ->assertNoJavaScriptErrors();

    expect($user->refresh()->balance->cents())->toBe(0);
});

it('lets Stripe pay everything', function () {
    PaymentGateways::fake();
    $this->actingAs(hostedShopper(500));

    openBasketWithPeas()
        ->click('Pickup')
        ->assertSee('Wallet $5.00 · Stripe $5.00')
        ->click('label:has([data-test="wallet-switch"])')
        ->assertSee('Pay $10.00 with Stripe')
        ->assertDontSee('Wallet $5.00 · Stripe $5.00')
        ->assertNoJavaScriptErrors();
});
