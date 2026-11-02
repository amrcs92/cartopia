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
