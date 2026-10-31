<?php

use App\Models\User;
use App\Support\Money;

it('tops up the wallet', function () {
    $user = User::factory()->withBalance(Money::fromCents(10000))->create();

    $this->actingAs($user);

    // A quick amount fills the field and the button says what will be added.
    visit('/wallet')
        ->click('[data-test="quick-amount-25"]')
        ->assertValue('#top-up-amount', '25')
        ->assertSee('Add $25.00 to my wallet')
        ->fill('#top-up-amount', '20')
        ->assertSee('Add $20.00 to my wallet')
        ->press('[data-test="top-up"]')
        ->waitForText('Added $20.00 to your balance.')
        ->assertSee('Added $20.00 to your balance.')
        ->assertNoJavaScriptErrors();
});
