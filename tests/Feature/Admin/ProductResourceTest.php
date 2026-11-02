<?php

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    fakePublicDisk();
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

function validProductData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Organic Apples',
        'short_name' => 'Apples',
        'slug' => 'organic-apples',
        'sku' => 'APL-001',
        'category_id' => null,
        'price' => '12.50',
        'description' => 'Crisp and sweet.',
        'is_featured' => true,
        'is_visible' => true,
        'photos' => [],
    ], $overrides);
}

function photo(string $name = 'a.jpg', int $w = 1200, int $h = 1200): UploadedFile
{
    return UploadedFile::fake()->image($name, $w, $h);
}

/** Errors the photos field reports, as plain messages. */
function photoErrors($component): array
{
    return collect($component->errors()->get('data.photos'))
        ->merge($component->errors()->get('data.photos.*'))
        ->flatten()
        ->all();
}

it('lists products with their columns', function () {
    $aisle = Category::factory()->create(['name' => 'Fruit']);
    $product = Product::factory()->for($aisle)->featured()->create(['name' => 'Apples', 'price' => 1250]);
    $hidden = Product::factory()->hidden()->create(['name' => 'Zucchini']);

    Livewire::test(ListProducts::class)
        ->assertCanSeeTableRecords([$product, $hidden], inOrder: true)
        ->assertTableColumnStateSet('category.name', 'Fruit', $product)
        ->assertTableColumnStateSet('price', '$12.50', $product)
        ->assertTableColumnStateSet('is_featured', true, $product)
        ->assertTableColumnStateSet('is_visible', false, $hidden)
        ->assertTableColumnExists('updated_at')
        ->assertTableColumnExists('image');
});

it('searches by name and by SKU', function () {
    $apples = Product::factory()->create(['name' => 'Apples', 'sku' => 'AAA-111']);
    $pears = Product::factory()->create(['name' => 'Pears', 'sku' => 'BBB-222']);

    Livewire::test(ListProducts::class)
        ->searchTable('Apples')
        ->assertCanSeeTableRecords([$apples])
        ->assertCanNotSeeTableRecords([$pears])
        ->searchTable('BBB-222')
        ->assertCanSeeTableRecords([$pears])
        ->assertCanNotSeeTableRecords([$apples]);
});

it('filters by aisle, featured and visible', function () {
    $fruit = Category::factory()->create();
    $dairy = Category::factory()->create();
    $apples = Product::factory()->for($fruit)->featured()->create();
    $milk = Product::factory()->for($dairy)->hidden()->create();

    Livewire::test(ListProducts::class)
        ->filterTable('category_id', $fruit->id)
        ->assertCanSeeTableRecords([$apples])->assertCanNotSeeTableRecords([$milk])
        ->resetTableFilters()
        ->filterTable('is_featured', true)
        ->assertCanSeeTableRecords([$apples])->assertCanNotSeeTableRecords([$milk])
        ->resetTableFilters()
        ->filterTable('is_visible', false)
        ->assertCanSeeTableRecords([$milk])->assertCanNotSeeTableRecords([$apples]);
});

it('creates a product with three photos stored in order', function () {
    $aisle = Category::factory()->create();

    Livewire::test(CreateProduct::class)
        ->fillForm(validProductData([
            'category_id' => $aisle->id,
            'photos' => [photo('one.jpg'), photo('two.jpg'), photo('three.jpg')],
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::where('sku', 'APL-001')->firstOrFail();

    expect($product->name)->toBe('Organic Apples')
        ->and($product->short_name)->toBe('Apples')
        ->and($product->slug)->toBe('organic-apples')
        ->and($product->category_id)->toBe($aisle->id)
        ->and($product->price->cents())->toBe(1250)
        ->and($product->is_featured)->toBeTrue()
        ->and($product->is_visible)->toBeTrue()
        ->and($product->images)->toHaveCount(3)
        ->and($product->images->pluck('sort_order')->all())->toBe([0, 1, 2]);

    foreach ($product->images as $image) {
        expect($image->path)->toStartWith('products/');
        Storage::disk('public')->assertExists($image->path);
    }
});

it('fills the slug from the name until it is edited by hand', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm(['name' => 'Fresh Milk 2L'])
        ->assertFormSet(['slug' => 'fresh-milk-2l'])
        ->fillForm(['slug' => 'my-milk'])
        ->fillForm(['name' => 'Fresh Milk 1L'])
        ->assertFormSet(['slug' => 'my-milk']);
});

it('defaults a new product to visible', function () {
    Livewire::test(CreateProduct::class)->assertFormSet(['is_visible' => true]);
});

it('validates the text fields', function () {
    Product::factory()->create(['slug' => 'taken', 'sku' => 'TAKEN']);

    Livewire::test(CreateProduct::class)
        ->fillForm(validProductData(['name' => '', 'description' => '']))
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'description' => 'required']);

    Livewire::test(CreateProduct::class)
        ->fillForm(validProductData(['name' => str_repeat('a', 121), 'short_name' => str_repeat('b', 19)]))
        ->call('create')
        ->assertHasFormErrors(['name' => 'max', 'short_name' => 'max']);

    Livewire::test(CreateProduct::class)
        ->fillForm(validProductData(['slug' => 'taken', 'sku' => 'TAKEN']))
        ->call('create')
        ->assertHasFormErrors(['slug' => 'unique', 'sku' => 'unique']);

    Livewire::test(CreateProduct::class)
        ->fillForm(validProductData(['slug' => 'Not Valid_']))
        ->call('create')
        ->assertHasFormErrors(['slug' => 'regex']);
});

it('keeps the product own slug and SKU valid on edit', function () {
    $product = Product::factory()->create(['slug' => 'mine', 'sku' => 'MINE']);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['name' => 'Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->name)->toBe('Renamed');
});

it('validates the price range and format', function (string $price, bool $ok) {
    $component = Livewire::test(CreateProduct::class)
        ->fillForm(validProductData(['price' => $price]))
        ->call('create');

    $ok ? $component->assertHasNoFormErrors() : $component->assertHasFormErrors(['price']);
})->with([
    'too low' => ['0', false],
    'a cent' => ['0.01', true],
    'the top' => ['10000', true],
    'too high' => ['10000.01', false],
    'three decimals' => ['1.234', false],
    'text' => ['abc', false],
]);

it('shows the price in dollars on edit and stores cents', function () {
    $product = Product::factory()->create(['price' => 1205]);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->assertFormSet(['price' => '12.05'])
        ->fillForm(['price' => '3.5'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->price->cents())->toBe(350);
});

it('refuses more than five photos', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm(validProductData(['photos' => array_map(fn ($i) => photo("p{$i}.jpg"), range(1, 6))]))
        ->call('create')
        ->assertHasFormErrors(['photos']);

    expect(Product::count())->toBe(0);
});

it('accepts five photos', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm(validProductData(['photos' => array_map(fn ($i) => photo("p{$i}.jpg"), range(1, 5))]))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ProductImage::count())->toBe(5);
});

it('refuses a photo that breaks the rules, saying why', function (Closure $makeFile, string $message) {
    $component = Livewire::test(CreateProduct::class)
        ->fillForm(validProductData(['photos' => [$makeFile()]]))
        ->call('create')
        ->assertHasFormErrors(['photos']);

    expect(photoErrors($component))->toContain($message);
    expect(Product::count())->toBe(0);
})->with([
    'a PNG' => [fn () => UploadedFile::fake()->image('a.png', 1200, 1200), 'Photos need to be JPG or WebP.'],
    'over 1 MB' => [fn () => UploadedFile::fake()->image('a.jpg', 1200, 1200)->size(1200), 'Photos need to be under 1 MB.'],
    'not square' => [fn () => UploadedFile::fake()->image('a.jpg', 1200, 900), 'Photos need to be square.'],
    'too small' => [fn () => UploadedFile::fake()->image('a.jpg', 900, 900), 'This photo is 900 × 900px. Photos need to be at least 1200 × 1200px.'],
]);

it('edits a product and reorders its photos so the sort order follows', function () {
    $product = Product::factory()->create();
    foreach (['a', 'b', 'c'] as $i => $name) {
        Storage::disk('public')->put("products/{$name}.jpg", 'x');
        ProductImage::factory()->for($product)->create(['path' => "products/{$name}.jpg", 'sort_order' => $i]);
    }

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->assertFormSet(fn (array $state) => expect(array_values($state['photos']))->toBe(['products/a.jpg', 'products/b.jpg', 'products/c.jpg']))
        ->fillForm(['photos' => ['products/c.jpg', 'products/a.jpg', 'products/b.jpg']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->images()->pluck('path')->all())->toBe(['products/c.jpg', 'products/a.jpg', 'products/b.jpg'])
        ->and($product->images()->pluck('sort_order')->all())->toBe([0, 1, 2]);
});

it('deletes a removed photo and its file, and adds a new one', function () {
    $product = Product::factory()->create();
    foreach (['a', 'b'] as $i => $name) {
        Storage::disk('public')->put("products/{$name}.jpg", 'x');
        ProductImage::factory()->for($product)->create(['path' => "products/{$name}.jpg", 'sort_order' => $i]);
    }

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['photos' => ['products/b.jpg', photo('new.jpg')]])
        ->call('save')
        ->assertHasNoFormErrors();

    $paths = $product->images()->pluck('path')->all();
    $added = collect($paths)->first(fn (string $path) => $path !== 'products/b.jpg');

    expect($paths)->toHaveCount(2)
        ->and($paths)->toContain('products/b.jpg')
        ->and($added)->toStartWith('products/')->not->toBe('products/a.jpg')
        ->and($product->images()->pluck('sort_order')->all())->toBe([0, 1]);
    Storage::disk('public')->assertMissing('products/a.jpg');
    Storage::disk('public')->assertExists($added);
    expect(ProductImage::where('path', 'products/a.jpg')->exists())->toBeFalse();
});

it('saves a seeded product without touching its photos', function () {
    $product = Product::factory()->withImages(2)->create();
    $before = $product->images()->get(['path', 'sort_order'])->toArray();

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['name' => 'Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->name)->toBe('Renamed')
        ->and($product->images()->get(['path', 'sort_order'])->toArray())->toBe($before);
});

it('does not re-check existing photos', function () {
    $product = Product::factory()->create();
    Storage::disk('public')->put('products/old.jpg', 'tiny and not an image');
    ProductImage::factory()->for($product)->create(['path' => 'products/old.jpg', 'sort_order' => 0]);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['description' => 'New words'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->images()->count())->toBe(1);
});

it('has no delete action anywhere', function () {
    $product = Product::factory()->create();

    expect(ProductResource::canDelete($product))->toBeFalse();

    Livewire::test(ListProducts::class)
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->assertActionDoesNotExist('delete');
});

it('is in the Products navigation slot', function () {
    expect(ProductResource::getNavigationLabel())->toBe('Products')
        ->and(ProductResource::getNavigationIcon())->toBe('heroicon-o-shopping-bag')
        ->and(ProductResource::getNavigationSort())->toBe(10);
});
