<?php

use App\Models\Product;

// The photo, arrow, thumbnail and lightbox buttons are found by their
// accessible names (aria-label), like the other icon-only buttons.

it('moves between photos with the arrows and the thumbnails', function () {
    $product = Product::factory()->withImages(3)->create(['name' => 'Apple']);

    visit('/products/'.$product->slug)
        ->assertSeeIn('[data-test=photo-counter]', '1 / 3')
        ->click('[aria-label="Next photo"]')
        ->assertSeeIn('[data-test=photo-counter]', '2 / 3')
        ->assertScript('document.querySelector("[aria-label=\'Show photo 2 of 3\']").getAttribute("aria-pressed")', 'true')
        ->click('[aria-label="Previous photo"]')
        ->click('[aria-label="Previous photo"]')
        ->assertSeeIn('[data-test=photo-counter]', '3 / 3')
        ->click('[aria-label="Show photo 1 of 3"]')
        ->assertSeeIn('[data-test=photo-counter]', '1 / 3')
        ->assertNoJavaScriptErrors();
});

it('shows one photo without arrows or thumbnails', function () {
    $product = Product::factory()->withImages(1)->create(['name' => 'Apple']);

    visit('/products/'.$product->slug)
        ->assertPresent('[aria-label="Open photo 1 of 1 in a larger view"]')
        ->assertNotPresent('[aria-label="Next photo"]')
        ->assertNotPresent('[aria-label="Show photo 1 of 1"]')
        ->assertNotPresent('[data-test=photo-counter]');
});

it('shows the marked area enlarged beside the photo while the mouse is on it', function () {
    $product = Product::factory()->withImages(2)->create(['name' => 'Apple']);

    visit('/products/'.$product->slug)->resize(1280, 900)
        ->assertNotPresent('[data-test=zoom-panel]')
        ->hover('[aria-label="Open photo 1 of 2 in a larger view"]')
        ->assertPresent('[data-test=zoom-panel]')
        ->assertPresent('[data-test=zoom-area]')
        ->hover('nav[aria-label="Breadcrumb"]')
        ->assertNotPresent('[data-test=zoom-panel]');
});

it('opens the photo in the lightbox, moves with the keys and closes', function () {
    $product = Product::factory()->withImages(3)->create(['name' => 'Apple']);

    visit('/products/'.$product->slug)
        ->click('[aria-label="Next photo"]')
        ->click('[aria-label="Open photo 2 of 3 in a larger view"]')
        ->waitForText('Photo 2 of 3')
        ->keys('[data-test=lightbox]', 'ArrowRight')
        ->assertSeeIn('[data-test=lightbox]', 'Photo 3 of 3')
        ->click('[data-test=lightbox] [aria-label="Show photo 1 of 3"]')
        ->assertSeeIn('[data-test=lightbox]', 'Photo 1 of 3')
        ->click('[data-test=lightbox] [aria-label="Zoom in"]')
        ->assertPresent('[data-test=lightbox] [aria-label="Zoom out"]')
        ->click('Close')
        ->wait(0.8)
        ->assertNotPresent('[data-test=lightbox]')
        ->assertSeeIn('[data-test=photo-counter]', '1 / 3');
});

it('closes the lightbox with Escape', function () {
    $product = Product::factory()->withImages(2)->create(['name' => 'Apple']);

    visit('/products/'.$product->slug)
        ->click('[aria-label="Open photo 1 of 2 in a larger view"]')
        ->waitForText('Photo 1 of 2')
        ->keys('[data-test=lightbox]', 'Escape')
        ->wait(0.8)
        ->assertNotPresent('[data-test=lightbox]');
});

it('fits the gallery and the lightbox on a phone', function () {
    $product = Product::factory()->withImages(4)->create(['name' => 'Apple']);

    visit('/products/'.$product->slug)->resize(360, 800)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('[aria-label="Open photo 1 of 4 in a larger view"]')
        ->waitForText('Photo 1 of 4')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});
