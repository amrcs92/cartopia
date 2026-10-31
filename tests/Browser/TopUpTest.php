<?php

use App\Models\User;
use App\Support\Money;

it('tops up the wallet', function () {
    $user = User::factory()->withBalance(Money::fromCents(10000))->create();

    $this->actingAs($user);

    visit('/wallet')
        ->fill('#top-up-amount', '20')
        ->press('Add credit')
        ->waitForText('Added $20.00 to your balance.')
        ->assertSee('Added $20.00 to your balance.')
        ->assertNoJavaScriptErrors();
});
