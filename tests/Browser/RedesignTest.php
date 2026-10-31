<?php

// The theme and menu buttons are icon-only, so they are found by their
// accessible names (aria-label); the plugin's click('text') matches visible
// text only.

it('switches to dark mode and keeps it after a reload', function () {
    visit('/')->click('[aria-label="Switch to dark mode"]')
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->refresh()
        ->assertScript('document.documentElement.classList.contains("dark")', true);
});

it('opens the phone menu and never scrolls sideways at 360px', function () {
    visit('/')->resize(360, 800)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('[aria-label="Open menu"]')->assertSee('Log in');
});
