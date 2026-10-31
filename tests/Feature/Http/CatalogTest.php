<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
});

it('shows the four newest products on the welcome page', function () {
    $products = Product::factory()->count(5)->withImages()->create();
    $newest = $products[4];

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('WelcomePage')
            ->has('counter', 4)
            ->where('counter.0.id', $newest->id)
            ->where('counter.0.slug', $newest->slug)
            ->where('counter.0.price', $newest->price->format())
            ->where('counter.0.image_url', Storage::disk('public')->url($newest->images()->first()->path))
            ->where('counter.3.id', $products[1]->id)
        );
});

it('paginates products twelve per page and keeps the query string', function () {
    Product::factory()->count(13)->create();

    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/ProductsIndexPage')
            ->has('products.data', 12)
        );

    $this->get(route('products.index').'?q=&page=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1));

    $this->get(route('products.index', ['sort' => 'price_asc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.links.next', fn (string $url) => str_contains($url, 'sort=price_asc') && str_contains($url, 'page=2'))
            ->where('products.meta.links', fn ($links) => collect($links)
                ->pluck('url')
                ->filter()
                ->every(fn (string $url) => str_contains($url, 'sort=price_asc')))
        );
});

it('applies filters and echoes them back', function () {
    $match = Product::factory()->create(['name' => 'Golden Apple', 'price' => 250]);
    $cheaper = Product::factory()->create(['name' => 'Green Apple', 'price' => 150]);
    Product::factory()->create(['name' => 'Cheap Apple', 'price' => 50]);
    Product::factory()->create(['name' => 'Pricey Apple', 'price' => 350]);
    Product::factory()->create(['name' => 'Golden Beer', 'price' => 200]);
    $lowRated = Product::factory()->create(['name' => 'Sour Apple', 'price' => 200]);
    Rating::factory()->for($match)->create(['stars' => 5]);
    Rating::factory()->for($match)->create(['stars' => 4]);
    Rating::factory()->for($cheaper)->create(['stars' => 4]);
    Rating::factory()->for($lowRated)->create(['stars' => 2]);

    $query = ['q' => 'apple', 'min_price' => '1.00', 'max_price' => '3.00', 'rating' => '4', 'sort' => 'price_desc'];

    $this->get(route('products.index', $query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/ProductsIndexPage')
            ->has('products.data', 2)
            ->where('products.data.0.id', $match->id)
            ->where('products.data.0.rating_average', 4.5)
            ->where('products.data.0.rating_count', 2)
            ->where('products.data.1.id', $cheaper->id)
            ->where('filters', [...$query, 'aisle' => null, 'rated' => null, 'featured' => null])
        );
});

it('echoes null filters when none are given', function () {
    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['q' => null, 'min_price' => null, 'max_price' => null, 'rating' => null, 'sort' => null, 'aisle' => null, 'rated' => null, 'featured' => null])
        );
});

it('accepts a max price on its own', function () {
    Product::factory()->create(['price' => 250]);
    Product::factory()->create(['price' => 350]);

    $this->get(route('products.index', ['max_price' => '3']))
        ->assertOk()
        ->assertSessionHasNoErrors()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1));
});

it('rejects invalid filters', function (array $query, string $field) {
    $this->get(route('products.index', $query))->assertSessionHasErrors($field);
})->with([
    'unknown sort' => [['sort' => 'bogus'], 'sort'],
    'max below min' => [['max_price' => '1', 'min_price' => '2'], 'max_price'],
    'rating above five' => [['rating' => '6'], 'rating'],
    'rating below one' => [['rating' => '0'], 'rating'],
    'negative price' => [['min_price' => '-1'], 'min_price'],
    'too many decimals' => [['max_price' => '1.234'], 'max_price'],
    'leading dot' => [['min_price' => '.5'], 'min_price'],
    'trailing dot' => [['min_price' => '5.'], 'min_price'],
    'plus sign' => [['max_price' => '+5'], 'max_price'],
    'long search' => [['q' => str_repeat('a', 101)], 'q'],
    'huge max price' => [['max_price' => '100000000000000000'], 'max_price'],
    'huge min price' => [['min_price' => '100000000000000000'], 'min_price'],
    'eight integer digits' => [['max_price' => '10000000'], 'max_price'],
]);

it('accepts the largest dollar amount', function () {
    $this->get(route('products.index', ['min_price' => '9999999.99', 'max_price' => '9999999.99']))
        ->assertOk()
        ->assertSessionHasNoErrors()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 0)
            ->where('filters.max_price', '9999999.99')
        );
});

it("shows a product by slug with images and the viewer's rating", function () {
    $product = Product::factory()->withImages(2)->create(['description' => 'Fresh and crunchy']);
    $rater = User::factory()->create();
    Rating::factory()->for($product)->for($rater)->create(['stars' => 4]);
    Rating::factory()->for($product)->create(['stars' => 5]);
    $urls = $product->images()->pluck('path')
        ->map(fn (string $path) => Storage::disk('public')->url($path))
        ->all();

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/ProductShowPage')
            ->where('product.id', $product->id)
            ->where('product.slug', $product->slug)
            ->where('product.description', 'Fresh and crunchy')
            ->where('product.images', $urls)
            ->where('product.image_url', $urls[0])
            ->where('product.rating_average', 4.5)
            ->where('product.rating_count', 2)
            ->where('product.my_rating', null)
        );

    $this->actingAs($rater)
        ->get('/products/'.$product->slug)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('product.my_rating', 4));
});

it('returns 404 for an unknown slug', function () {
    $this->get('/products/no-such-product')->assertNotFound();
});

it('never exposes internal columns', function () {
    $product = Product::factory()->create();

    $this->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('products.data.0.sku')
            ->missing('products.data.0.created_at')
            ->missing('products.data.0.description')
            ->missing('products.data.0.ratings_avg_stars')
        );

    $this->get(route('products.show', $product))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('product.sku')
            ->missing('product.created_at')
            ->missing('product.updated_at')
        );
});

it('shows at most six aisles with products, most stocked first', function () {
    $big = Category::factory()->create(['name' => 'Big']);
    Product::factory()->count(3)->inCategory($big)->create();
    Category::factory()->count(6)->create()->each(fn ($c) => Product::factory()->inCategory($c)->create());
    Category::factory()->create(['name' => 'Empty']);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->has('aisles', 6)
        ->where('aisles.0.name', 'Big')
        ->where('aisles.0.product_count', 3)
    );
});

it('shows up to eight featured products, newest first, and nothing when none are featured', function () {
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('featured', 0));

    $products = Product::factory()->count(9)->featured()->create();
    Product::factory()->create();

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->has('featured', 8)
        ->where('featured.0.id', $products[8]->id)
    );
});

it('filters the shop by one or more aisles', function () {
    $drinks = Category::factory()->create(['name' => 'Drinks', 'slug' => 'drinks']);
    $dairy = Category::factory()->create(['name' => 'Dairy', 'slug' => 'dairy']);
    Product::factory()->inCategory($drinks)->create();
    Product::factory()->inCategory($dairy)->create();
    Product::factory()->create(['category_id' => null]);

    $this->get(route('products.index', ['aisle' => ['drinks']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.aisle', 'Drinks')
            ->where('filters.aisle', ['drinks'])
            ->has('aisles', 2)
        );

    $this->get(route('products.index', ['aisle' => ['drinks', 'dairy']]))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 2));

    $this->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 3));
});

it('filters the shop to rated products and to featured products', function () {
    $rated = Product::factory()->create();
    Rating::factory()->for($rated)->create(['stars' => 3]);
    $featured = Product::factory()->featured()->create();
    Product::factory()->create();

    $this->get(route('products.index', ['rated' => '1']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $rated->id)
            ->where('filters.rated', '1')
        );

    $this->get(route('products.index', ['featured' => '1']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $featured->id)
            ->has('featured', 1)
        );

    $this->get(route('products.index', ['featured' => '0', 'rated' => '0']))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 3));
});

it('combines aisle with search and price filters', function () {
    $drinks = Category::factory()->create(['slug' => 'drinks']);
    $match = Product::factory()->inCategory($drinks)->create(['name' => 'Spring Water', 'price' => 100]);
    Product::factory()->inCategory($drinks)->create(['name' => 'Spring Water Large', 'price' => 200]);
    Product::factory()->inCategory($drinks)->create(['name' => 'Cola', 'price' => 100]);
    Product::factory()->create(['name' => 'Water Pistol', 'price' => 100]);

    $this->get(route('products.index', ['aisle' => ['drinks'], 'q' => 'wat', 'max_price' => '1.50']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $match->id)
        );
});

it('rejects bad aisle input without a server error', function (array|string $aisle, string $field) {
    Category::factory()->create(['slug' => 'drinks']);

    $this->get(route('products.index', ['aisle' => $aisle]))->assertSessionHasErrors($field);
})->with([
    'unknown slug' => [['nope'], 'aisle.0'],
    'not an array' => ['drinks', 'aisle'],
    'nested array' => [[['x']], 'aisle.0'],
    'too many' => [array_fill(0, 21, 'drinks'), 'aisle'],
]);

it('treats a repeated aisle as one', function () {
    $drinks = Category::factory()->create(['slug' => 'drinks']);
    Product::factory()->inCategory($drinks)->create();
    Product::factory()->create();

    $this->get(route('products.index', ['aisle' => ['drinks', 'drinks']]))
        ->assertOk()
        ->assertSessionHasNoErrors()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
        );
});

it('shows the breadcrumb aisle on the product page, or none', function () {
    $drinks = Category::factory()->create(['name' => 'Drinks', 'slug' => 'drinks']);
    $product = Product::factory()->inCategory($drinks)->create();
    $loose = Product::factory()->create(['category_id' => null]);

    $this->get(route('products.show', $product))->assertInertia(fn (Assert $page) => $page
        ->where('product.category', ['name' => 'Drinks', 'slug' => 'drinks'])
    );
    $this->get(route('products.show', $loose))->assertInertia(fn (Assert $page) => $page
        ->where('product.category', null)
    );
});

it('leaves the current product out of its featured carousel', function () {
    $product = Product::factory()->featured()->create();
    $others = Product::factory()->count(2)->featured()->create();

    $this->get(route('products.show', $product))->assertInertia(fn (Assert $page) => $page
        ->has('featured', 2)
        ->where('featured', fn ($featured) => collect($featured)->pluck('id')->doesntContain($product->id))
    );
});

it('keeps the chosen sort when filtering to featured products', function () {
    $dear = Product::factory()->featured()->create(['price' => 300]);
    $cheap = Product::factory()->featured()->create(['price' => 100]);
    $mid = Product::factory()->featured()->create(['price' => 200]);
    Product::factory()->create(['price' => 50]);

    $this->get(route('products.index', ['featured' => '1', 'sort' => 'price_asc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 3)
            ->where('products.data.0.id', $cheap->id)
            ->where('products.data.1.id', $mid->id)
            ->where('products.data.2.id', $dear->id)
        );
});
