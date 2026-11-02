<?php

use App\Models\User;

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
