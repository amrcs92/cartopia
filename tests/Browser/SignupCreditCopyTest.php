<?php

use App\Models\StoreSettings;
use App\Support\Money;

it('says the sign-up credit admins set across the store', function () {
    StoreSettings::current()->update(['signup_credit' => Money::fromCents(2500)]);

    visit('/')
        ->assertSee('New accounts start with $25 of credit.')
        ->assertSee('Sign up and get $25 credit')
        ->assertSee('New here? Your first $25 is on us.')
        ->assertSee('$25 of credit when you create an account')
        ->assertSee('Every new account starts with $25 of credit.')
        ->assertDontSee('$100')
        ->assertNoJavaScriptErrors();

    visit('/register')->assertSee('$25 of credit when you create an account');
});

it('hides every credit promise when the sign-up credit is off', function () {
    StoreSettings::current()->update(['signup_credit' => Money::zero()]);

    visit('/')
        ->assertSee('Free pickup on every order.')
        ->assertDontSee('New accounts')
        ->assertDontSee('Sign up and get')
        ->assertDontSee('is on us')
        ->assertDontSee('of credit when you create an account')
        ->assertSee('Fresh picks, every day.')
        ->assertSee('Your balance pays for orders at checkout')
        ->assertDontSee('Every new account starts with')
        ->assertNoJavaScriptErrors();

    visit('/register')
        ->assertSee('Why sign up?')
        ->assertDontSee('of credit when you create an account');
});
