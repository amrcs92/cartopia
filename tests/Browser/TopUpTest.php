<?php

use App\Models\User;
use App\Payments\PaymentGateways;
use App\Support\Money;

it('tops up the wallet', function () {
    PaymentGateways::fake();
    $user = User::factory()->withBalance(Money::fromCents(10000))->create();

    $this->actingAs($user);

    // A quick amount fills the field and the button says what will be paid.
    // The fake provider sends the browser straight back with the payment approved.
    visit('/wallet')
        ->click('[data-test="quick-amount-25"]')
        ->assertValue('#top-up-amount', '25')
        ->assertSee('Continue to pay $25.00')
        ->fill('#top-up-amount', '20')
        ->assertSee('Continue to pay $20.00')
        ->press('[data-test="top-up"]')
        // Wait on the ledger row, which stays; the toast fades after 4 seconds.
        // Its wording is covered in the feature tests.
        ->waitForText('Top-up with Stripe')
        ->assertSee('$120.00')
        ->assertNoJavaScriptErrors();

    expect($user->refresh()->balance->format())->toBe('$120.00');
});

it('does not show the top-up toast again when going Back', function () {
    PaymentGateways::fake();
    $user = User::factory()->withBalance(Money::fromCents(10000))->create();

    $this->actingAs($user);

    $page = visit('/wallet')
        ->fill('#top-up-amount', '20')
        ->press('[data-test="top-up"]')
        ->waitForText('Top-up with Stripe');
    $page->script('document.querySelector(\'a[href$="/products"]\').click()');
    $page->wait(5)
        ->assertDontSee('Added $20.00 to your wallet.')
        ->back()
        ->wait(1)
        ->assertDontSee('Added $20.00 to your wallet.')
        ->assertNoJavaScriptErrors();
});

it('says payments are not set up when no method is available', function () {
    $this->actingAs(User::factory()->create());

    visit('/wallet')
        ->assertSee("Stripe and PayPal payments aren't set up on this copy of Cartopia.")
        ->assertDontSee('Pay with')
        ->assertScript('document.querySelector(\'[data-test="top-up"]\').disabled', true)
        ->assertNoJavaScriptErrors();
});
