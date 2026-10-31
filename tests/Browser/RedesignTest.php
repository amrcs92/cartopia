<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ShippingMethod;
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

// Carousel helpers: the live region's text, and each slide as "--" (in view)
// or "ih" (inert and aria-hidden).
const CAROUSEL_LIVE = 'document.querySelector("[aria-roledescription=carousel] [aria-live]").textContent';
const CAROUSEL_SLIDES = '[...document.querySelectorAll("[aria-roledescription=slide]")].map(s => (s.inert ? "i" : "-") + (s.getAttribute("aria-hidden") === "true" ? "h" : "-")).join("")';

it('wraps the featured carousel to the last position when Previous is pressed', function () {
    Product::factory()->count(5)->featured()->create();

    visit('/')->assertScript(CAROUSEL_LIVE.'.includes("1 to 3")', true)
        ->click('[aria-label="Previous featured products"]')
        ->assertScript(CAROUSEL_LIVE.'.includes("3 to 5")', true)
        ->click('[aria-label="Next featured products"]')
        ->assertScript(CAROUSEL_LIVE.'.includes("1 to 3")', true);
});

it('moves the featured carousel to the position of a pressed dot', function () {
    Product::factory()->count(5)->featured()->create();

    visit('/')->click('[aria-label="Show products 3 to 5"]')
        ->assertScript(CAROUSEL_LIVE.'.includes("3 to 5")', true)
        ->assertScript('document.querySelector("[aria-label=\'Show products 3 to 5\']").getAttribute("aria-current")', 'true')
        ->assertScript('document.querySelector("[aria-label=\'Show products 1 to 3\']").hasAttribute("aria-current")', false);
});

it('keeps out-of-view featured slides away from the keyboard and screen readers', function () {
    Product::factory()->count(5)->featured()->create();

    visit('/')->assertScript(CAROUSEL_SLIDES, '------ihih')
        ->click('[aria-label="Next featured products"]')
        ->assertScript(CAROUSEL_SLIDES, 'ih------ih');
});

it('labels shipping methods as pickup or delivery by name, not by fee', function () {
    ShippingMethod::factory()->create(['name' => 'Pickup', 'fee' => 0]);
    ShippingMethod::factory()->create(['name' => 'Courier', 'fee' => 0]);
    ShippingMethod::factory()->create(['name' => 'UPS', 'fee' => 500]);

    visit('/')->assertScript(
        '[...document.querySelectorAll("[data-test=shipping-method]")].map(e => e.innerText.replace(/\\s+/g, " ").trim()).join(" | ")',
        'Pickup Collect your order yourself. Free | Courier Delivered to your address. Free | UPS Delivered to your address. $5.00',
    )->assertSee('Pick it up for free, or have it delivered: Courier for free, or UPS for $5.00.');
});

it('keeps the delivery copy neutral when there are no shipping methods', function () {
    visit('/')->assertSee('Choose how to get it at checkout.')
        ->assertSee('Delivery options are shown at checkout.')
        ->assertPresent('#delivery');
});

it('narrows the grid when an aisle is ticked', function () {
    $drinks = Category::factory()->create(['name' => 'Drinks', 'slug' => 'drinks']);
    Product::factory()->inCategory($drinks)->create(['name' => 'Water']);
    Product::factory()->create(['name' => 'Apple']);
    visit('/products')->check('Drinks')
        ->waitForText('1 product')
        ->assertSee('Water')
        ->assertDontSee('Apple')
        ->assertScript('location.search.includes("aisle%5B%5D=drinks") || location.search.includes("aisle[]=drinks")', true)
        ->assertScript('location.search.includes("aisle%5B0%5D") || location.search.includes("aisle[0]")', false);
});

// "Show 1 product" is also a substring of "Show 1 products", so the old count
// is checked as gone too.
it('opens the filters sheet on a phone and shows the matching total', function () {
    Product::factory()->count(2)->create();
    Product::factory()->featured()->create();

    visit('/products')->resize(360, 800)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('Filters')
        ->assertSee('Show 3 products')
        ->check('Featured products only')
        ->assertSee('Show 1 product')
        ->assertDontSee('Show 3 products')
        ->assertQueryStringHas('featured', '1');
});

// A chip's remove button is named "Remove <chip text>" through
// aria-labelledby, so it is found by its role and accessible name.
it('clears a filter from its chip when nothing matches', function () {
    Product::factory()->create(['name' => 'Apple', 'price' => 300]);

    visit('/products?q=mango&max_price=1')
        ->assertSee('Results for "mango"')
        ->assertSee('Nothing on the stall matches that')
        ->assertSee('Remove the price filter to see more.')
        ->click('internal:role=button[name="Remove Search: mango"s]')
        ->assertSee('All products')
        ->assertQueryStringMissing('q')
        ->click('internal:role=button[name="Remove Under $1"s]')
        ->waitForText('Apple')
        ->assertDontSee('Nothing on the stall matches that');
});

it('shows the aisle in the breadcrumb and rates a product with the stars', function () {
    $drinks = Category::factory()->create(['name' => 'Drinks', 'slug' => 'drinks']);
    $product = Product::factory()->inCategory($drinks)->create(['name' => 'Sparkling Water']);
    $this->actingAs(User::factory()->create());

    visit('/products/'.$product->slug)
        ->assertSeeIn('nav[aria-label="Breadcrumb"]', 'Drinks')
        ->assertSee('Add to basket')
        ->click('[aria-label="4 stars"]')
        ->assertSee('You rated this 4 out of 5.')
        ->assertScript('document.querySelector("[aria-label=\'4 stars\']").getAttribute("aria-checked")', 'true')
        ->keys('[aria-label="4 stars"]', 'ArrowRight')
        ->assertScript('document.activeElement.getAttribute("aria-label")', '5 stars')
        ->keys('[aria-label="5 stars"]', 'Enter')
        ->waitForText('You rated this 5 out of 5.');
});

it('opens the added-to-basket panel and closes it with Escape', function () {
    Product::factory()->create(['name' => 'Apple']);
    visit('/products')->click('[aria-label="Add Apple to basket"]')
        ->waitForText('Added to your basket')
        ->keys('[role="dialog"]', 'Escape')
        ->wait(0.8)
        ->assertNotPresent('[role="dialog"]');
});

it('does not reopen the added-to-basket panel when going back', function () {
    Product::factory()->create(['name' => 'Apple']);
    visit('/products')->click('[aria-label="Add Apple to basket"]')
        ->waitForText('Added to your basket')
        ->click('Keep shopping')
        ->wait(0.8)
        ->assertNotPresent('[role="dialog"]')
        ->click('a:has-text("Apple")')
        ->waitForText('Add to basket')
        ->back()
        ->waitForText('All products')
        ->wait(0.8)
        ->assertNotPresent('[role="dialog"]');
});

it('disables placing the order when the wallet cannot cover it', function () {
    $this->actingAs(User::factory()->create());
    $product = Product::factory()->create(['name' => 'Apple', 'price' => 1000]);
    ShippingMethod::factory()->pickup()->create();

    visit('/products/'.$product->slug)->click('Add to basket')
        ->waitForText('Added to your basket')
        ->click('View basket and check out')
        ->waitForText('You need $10.00 more.')
        ->assertSee('Top up your wallet')
        ->assertScript('document.querySelector("[data-test=checkout-button]").disabled', true);
});

it('shows the empty basket with a way back to the stall', function () {
    visit('/cart')->assertSee('Your basket is empty')
        ->assertSee('Browse the stall');
});
