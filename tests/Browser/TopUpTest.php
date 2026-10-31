<?php

use App\Models\User;
use App\Support\Money;

it('tops up the wallet', function () {
    $user = User::factory()->withBalance(Money::fromCents(10000))->create();

    $this->actingAs($user);

    // The card above the form is also titled "Add credit", and a text match
    // clicks the first element with that text, so press the button itself.
    visit('/wallet')
        ->fill('#top-up-amount', '20')
        ->press('[data-test="top-up"]')
        ->waitForText('Added $20.00 to your balance.')
        ->assertSee('Added $20.00 to your balance.')
        ->assertNoJavaScriptErrors();
});
