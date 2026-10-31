<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Rating;

it('searches name and description case-insensitively', function () {
    $apple = Product::factory()->create(['name' => 'Apple', 'description' => 'Crunchy fruit']);
    Product::factory()->create(['name' => 'Beer', 'description' => 'Golden lager']);

    expect(Product::query()->search('APP')->pluck('id')->all())->toBe([$apple->id])
        ->and(Product::query()->search('lager')->pluck('name')->all())->toBe(['Beer'])
        ->and(Product::query()->search(null)->count())->toBe(2)
        ->and(Product::query()->search('  ')->count())->toBe(2);
});

it('treats LIKE wildcards in the search term literally', function () {
    Product::factory()->create(['name' => '100% Juice', 'description' => 'Fresh']);
    Product::factory()->create(['name' => 'Apple', 'description' => 'Fresh']);

    expect(Product::query()->search('%')->pluck('name')->all())->toBe(['100% Juice'])
        ->and(Product::query()->search('_')->count())->toBe(0)
        ->and(Product::query()->search('!')->count())->toBe(0);
});

it('filters by inclusive price range', function () {
    foreach ([99, 100, 300, 301] as $price) {
        Product::factory()->create(['price' => $price]);
    }
    $cents = fn ($q) => $q->orderBy('price')->get()->map(fn ($p) => $p->price->cents())->all();

    expect($cents(Product::query()->priceBetween(100, 300)))->toBe([100, 300])
        ->and($cents(Product::query()->priceBetween(300, null)))->toBe([300, 301])
        ->and($cents(Product::query()->priceBetween(null, 99)))->toBe([99])
        ->and($cents(Product::query()->priceBetween(null, null)))->toHaveCount(4);
});

it('filters by minimum average rating and excludes unrated', function () {
    $good = Product::factory()->create();
    $mid = Product::factory()->create();
    Product::factory()->create();
    Rating::factory()->for($good)->create(['stars' => 5]);
    Rating::factory()->for($good)->create(['stars' => 4]);
    Rating::factory()->for($mid)->create(['stars' => 3]);
    Rating::factory()->for($mid)->create(['stars' => 4]);

    expect(Product::query()->minRating(4)->pluck('id')->all())->toBe([$good->id])
        ->and(Product::query()->minRating(null)->count())->toBe(3);
});

it('sorts by each option and defaults to newest', function (string $sort, array $expected) {
    $a = Product::factory()->create(['price' => 300]);
    $b = Product::factory()->create(['price' => 100]);
    $c = Product::factory()->create(['price' => 200]);
    Rating::factory()->for($a)->create(['stars' => 2]);
    Rating::factory()->for($b)->create(['stars' => 5]);

    $ids = ['a' => $a->id, 'b' => $b->id, 'c' => $c->id];
    $expectedIds = array_map(fn ($k) => $ids[$k], $expected);

    expect(Product::query()->sortBy($sort)->pluck('id')->all())->toBe($expectedIds);
})->with([
    'newest' => ['newest', ['c', 'b', 'a']],
    'price_asc' => ['price_asc', ['b', 'c', 'a']],
    'price_desc' => ['price_desc', ['a', 'c', 'b']],
    'rating' => ['rating', ['b', 'a', 'c']],
    'bogus' => ['bogus', ['c', 'b', 'a']],
]);

it('keeps SORTS in sync with the sort options', function () {
    expect(Product::SORTS)->toBe(['newest', 'price_asc', 'price_desc', 'rating']);
});

it('exposes rating stats', function () {
    $product = Product::factory()->create();
    Rating::factory()->for($product)->create(['stars' => 5]);
    Rating::factory()->for($product)->create(['stars' => 4]);

    $found = Product::query()->withRatingStats()->findOrFail($product->id);

    expect((float) $found->ratings_avg_stars)->toBe(4.5)
        ->and($found->ratings_count)->toBe(2);
});

it('lists only featured products, newest first', function () {
    $old = Product::factory()->featured()->create();
    Product::factory()->create();
    $new = Product::factory()->featured()->create();

    expect(Product::query()->featured()->pluck('id')->all())->toBe([$new->id, $old->id]);
});

it('filters by aisle slugs and ignores null or empty', function () {
    $drinks = Category::factory()->create(['slug' => 'drinks']);
    $dairy = Category::factory()->create(['slug' => 'dairy']);
    $other = Category::factory()->create(['slug' => 'other']);
    $a = Product::factory()->inCategory($drinks)->create();
    $b = Product::factory()->inCategory($dairy)->create();
    Product::factory()->inCategory($other)->create();
    Product::factory()->create();

    expect(Product::query()->inAisles(['drinks', 'dairy'])->orderBy('id')->pluck('id')->all())->toBe([$a->id, $b->id])
        ->and(Product::query()->inAisles(null)->count())->toBe(4)
        ->and(Product::query()->inAisles([])->count())->toBe(4);
});

it('keeps only products with at least one rating', function () {
    $rated = Product::factory()->create();
    Product::factory()->create();
    Rating::factory()->for($rated)->create(['stars' => 3]);
    Rating::factory()->for($rated)->create(['stars' => 4]);

    expect(Product::query()->rated()->pluck('id')->all())->toBe([$rated->id]);
});
