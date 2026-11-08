<?php

use App\Models\Product;

// The header search lists matching products in a dropdown as you type,
// without leaving the page.
const SUGGESTIONS = '[data-test="search-suggestions"]';
const SEARCH_BOX = '[role=combobox]';

it('lists matching products as you type and opens the highlighted one', function () {
    Product::factory()->create(['name' => 'Whole milk']);
    Product::factory()->create(['name' => 'Almond milk']);
    Product::factory()->create(['name' => 'Bread']);
    Product::factory()->hidden()->create(['name' => 'Goat milk']);

    visit('/')
        ->type(SEARCH_BOX, 'milk')
        ->waitForText('Almond milk')
        ->assertPathIs('/')
        ->assertSeeIn(SUGGESTIONS, 'Whole milk')
        ->assertDontSeeIn(SUGGESTIONS, 'Bread')
        ->assertDontSeeIn(SUGGESTIONS, 'Goat milk')
        ->assertSeeIn(SUGGESTIONS, 'See all 2 results for “milk”')
        ->assertAttribute(SEARCH_BOX, 'aria-expanded', 'true')
        ->keys(SEARCH_BOX, 'ArrowDown')
        ->keys(SEARCH_BOX, 'ArrowDown')
        ->assertSeeIn('[role=option][aria-selected=true]', 'Whole milk')
        ->keys(SEARCH_BOX, 'Enter')
        ->assertPathIs('/products/'.Product::firstWhere('name', 'Whole milk')->slug)
        ->assertNoJavaScriptErrors();
});

it('opens every result from the last row', function () {
    Product::factory()->create(['name' => 'Whole milk']);

    visit('/')
        ->type(SEARCH_BOX, 'milk')
        ->waitForText('See 1 result for “milk”')
        ->click('See 1 result for “milk”')
        ->assertPathIs('/products')
        ->assertQueryStringHas('q', 'milk')
        ->assertSee('Whole milk');
});

it('says when nothing matches and closes with Escape', function () {
    Product::factory()->create(['name' => 'Bread']);

    visit('/')
        ->type(SEARCH_BOX, 'zzz')
        ->waitForText('No products match “zzz”')
        ->keys(SEARCH_BOX, 'Escape')
        ->assertAttribute(SEARCH_BOX, 'aria-expanded', 'false')
        ->assertMissing(SUGGESTIONS);
});

it('waits for two letters before listing anything', function () {
    Product::factory()->create(['name' => 'Bread']);

    visit('/')
        ->type(SEARCH_BOX, 'b')
        ->wait(0.6)
        ->assertAttribute(SEARCH_BOX, 'aria-expanded', 'false')
        ->assertMissing(SUGGESTIONS);
});

it('shows the dropdown in the phone header too', function () {
    Product::factory()->create(['name' => 'Water']);

    visit('/')->resize(375, 800)
        ->type(SEARCH_BOX, 'wa')
        ->waitForText('See 1 result for “wa”')
        ->assertSeeIn(SUGGESTIONS, 'Water')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
});
