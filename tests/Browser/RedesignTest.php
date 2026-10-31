<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;

// The theme, menu and carousel arrow buttons are icon-only, so they are found
// by their accessible names (aria-label); the plugin's click('text') matches
// visible text only.

it('switches to dark mode and keeps it after a reload', function () {
    visit('/')->click('[aria-label="Switch to dark mode"]')
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->refresh()
        ->assertScript('document.documentElement.classList.contains("dark")', true);
});

it('opens the phone menu and never scrolls sideways at 360px', function () {
    // Featured products fill the carousel's swipeable row, which must scroll
    // on its own without widening the page.
    Product::factory()->count(4)->featured()->create();

    visit('/')->resize(360, 800)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('[aria-label="Open menu"]')->assertSee('Log in');
});

it('logs out from the account menu in the desktop header', function () {
    $this->actingAs(User::factory()->create(['name' => 'Ada Shopper']));

    visit('/')->click('[aria-label="Account menu"]')
        ->assertSee('Ada Shopper')
        ->assertSee('Account settings')
        ->click('[data-test="logout-button"]')
        ->assertSee('Log in')
        ->assertNoJavaScriptErrors();
});

it('slides the featured carousel when Next is pressed', function () {
    Product::factory()->count(5)->featured()->create();

    visit('/')->assertScript('document.querySelector("[aria-roledescription=carousel] [aria-live]").textContent.includes("1 to 3")', true)
        ->click('[aria-label="Next featured products"]')
        ->assertScript('document.querySelector("[aria-roledescription=carousel] [aria-live]").textContent.includes("2 to 4")', true);
});

// The arrows have no visible text, so "don't see" is checked on the DOM; the
// carousel itself must be there, or the check would pass on an empty page.
it('hides carousel arrows when every featured product already fits', function () {
    Product::factory()->count(2)->featured()->create();

    visit('/')->assertPresent('[aria-roledescription=carousel]')
        ->assertNotPresent('[aria-label="Next featured products"]')
        ->assertNotPresent('[aria-label="Previous featured products"]');
});

it('shows a basket on an aisle tile that has no photo', function () {
    $aisle = Category::factory()->create(['name' => 'Pantry']);
    Product::factory()->inCategory($aisle)->create();

    visit('/')->assertSee('Shop by aisle')
        ->assertSee('1 product')
        ->assertPresent('[data-test="aisle-tile-fallback"]');
});
