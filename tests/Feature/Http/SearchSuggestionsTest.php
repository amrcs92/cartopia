<?php

use App\Models\Product;
use App\Support\Money;

it('suggests visible products matching the search, name matches first', function () {
    Product::factory()->create(['name' => 'Whole milk', 'price' => Money::fromCents(189)]);
    Product::factory()->create(['name' => 'Cereal', 'description' => 'Pour on cold milk.']);
    Product::factory()->create(['name' => 'Almond milk']);
    Product::factory()->hidden()->create(['name' => 'Goat milk']);
    Product::factory()->create(['name' => 'Bread']);

    $response = $this->getJson(route('search.suggestions', ['q' => 'milk']))->assertOk();

    expect($response->json('query'))->toBe('milk')
        ->and($response->json('total'))->toBe(3)
        ->and(array_column($response->json('products'), 'name'))->toBe(['Almond milk', 'Whole milk', 'Cereal'])
        ->and($response->json('products.1'))->toMatchArray([
            'name' => 'Whole milk',
            'price' => '$1.89',
            'image_url' => null,
        ])
        ->and($response->json('products.1.url'))->toBe(route('products.show', Product::firstWhere('name', 'Whole milk')));
});

it('returns at most six products but counts every match', function () {
    Product::factory()->count(8)->sequence(fn ($s) => ['name' => "Tea {$s->index}"])->create();

    $response = $this->getJson(route('search.suggestions', ['q' => 'tea']))->assertOk();

    expect($response->json('products'))->toHaveCount(6)
        ->and($response->json('total'))->toBe(8);
});

it('ignores searches shorter than two letters', function (string $q) {
    Product::factory()->create(['name' => 'Apple']);

    $this->getJson(route('search.suggestions', ['q' => $q]))
        ->assertOk()
        ->assertExactJson(['query' => trim($q), 'total' => 0, 'products' => []]);
})->with(['', 'a', ' a ']);

it('treats % and _ as plain characters', function () {
    Product::factory()->create(['name' => 'Apple']);
    Product::factory()->create(['name' => '100% juice']);

    $response = $this->getJson(route('search.suggestions', ['q' => '0%']))->assertOk();

    expect(array_column($response->json('products'), 'name'))->toBe(['100% juice']);
});

it('limits how often the suggestions can be asked for', function () {
    foreach (range(1, 60) as $i) {
        $this->getJson(route('search.suggestions', ['q' => 'xy']))->assertOk();
    }

    $this->getJson(route('search.suggestions', ['q' => 'xy']))->assertTooManyRequests();
});
