<?php

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
        ->waitForText('Your Stripe payment of $15.00 was approved.')
        ->assertSee('Visa ending in 4242, through Stripe')
        ->assertNoJavaScriptErrors();

    expect(Payment::sole()->status->value)->toBe('succeeded')
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
        ->waitForText('Your PayPal payment of $15.00 was approved.')
        ->assertNoJavaScriptErrors();

    expect($user->refresh()->balance->format())->toBe('$100.00');
});

it('cancels and keeps the basket', function () {
    PaymentGateways::fake()->redirectToCancel();
    $this->actingAs(hostedShopper());

    openBasketWithPeas()
        ->click('UPS')
        ->click(payCard('stripe'))
        ->press('Pay $15.00 with Stripe')
        ->waitForText('Payment cancelled. Nothing was charged.')
        ->assertSee('Garden Peas')
        ->assertNoJavaScriptErrors();
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

it('splits a short wallet with Stripe', function () {
    PaymentGateways::fake();
    $user = hostedShopper(500);
    $this->actingAs($user);

    openBasketWithPeas()
        ->click('Pickup')
        ->assertSee('My $5.00 balance')
        ->assertSee('Wallet $5.00 · Stripe $5.00')
        ->press('Pay $5.00 with Stripe')
        ->waitForText('Order placed. $5.00 came from your wallet and your Stripe payment of $5.00 was approved.')
        ->waitForText('Wallet balance after')
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
