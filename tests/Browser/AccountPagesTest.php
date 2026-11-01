<?php

use App\Models\User;

it('shows the log in form beside the Why sign up chalkboard', function () {
    visit('/login')
        ->assertSee('Log in to your account')
        ->assertSee('Why sign up?')
        ->assertSee('$100 of credit when you create an account')
        ->assertSee('Shop as a guest; your basket comes with you when you log in')
        ->assertSee('Every order kept with the exact prices you paid')
        ->assertNoJavaScriptErrors();
});

it('offers Sign up next to Log in for guests', function () {
    visit('/')->assertSee('Log in')->assertSee('Sign up');
});

it('renders the not-found page for an unknown URL, with the store chrome', function () {
    visit('/no-such-page')
        ->assertSee('This page has packed up for the day')
        ->assertSee("We can't find that page")
        ->assertSee('Browse the shop')
        ->assertSee('Log in')
        ->assertNoJavaScriptErrors();
});

it('renders the settings pages for a signed-in user', function (string $path, string $text) {
    $this->actingAs(User::factory()->create());

    visit($path)
        ->assertSee('Settings')
        ->assertSee($text)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
})->with([
    ['/settings/profile', 'Update your name and email address'],
    ['/settings/appearance', 'Light'],
]);

// The security settings ask for the password first; that screen shares the
// account layout and its chalkboard.
it('asks for the password before the security settings', function () {
    $this->actingAs(User::factory()->create());

    visit('/settings/security')
        ->assertSee('Confirm password')
        ->assertSee('Why sign up?')
        ->assertNoJavaScriptErrors();
});
