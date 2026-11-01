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

it('pays by card and shows the card receipt', function () {
    PaymentGateways::fake();
    $user = hostedShopper();
    $this->actingAs($user);

    openBasketWithPeas()
        ->click('UPS')
        ->click(payCard('stripe'))
        ->press('Pay $15.00 by card')
        ->waitForText('Your card was charged $15.00.')
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
        ->press('Pay $15.00 by card')
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
        ->assertSee('Pay $15.00 by card')
        ->assertNoJavaScriptErrors();
});
