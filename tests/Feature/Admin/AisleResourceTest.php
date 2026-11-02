<?php

use App\Filament\Resources\Aisles\Pages\CreateAisle;
use App\Filament\Resources\Aisles\Pages\EditAisle;
use App\Filament\Resources\Aisles\Pages\ListAisles;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
});

function tile(string $name = 'tile.jpg', int $w = 1200, int $h = 1200): UploadedFile
{
    return UploadedFile::fake()->image($name, $w, $h);
}

/** Errors the tile field reports, as plain messages. */
function tileErrors($component): array
{
    return collect($component->errors()->get('data.image_path'))->flatten()->all();
}

it('lists aisles with their tile, name and product count', function () {
    $fruit = Category::factory()->create(['name' => 'Fruit', 'image_path' => 'aisles/fruit.jpg']);
    Product::factory()->count(3)->for($fruit)->create();
    $empty = Category::factory()->create(['name' => 'Zero']);

    Livewire::test(ListAisles::class)
        ->assertCanSeeTableRecords([$fruit, $empty], inOrder: true)
        ->assertTableColumnExists('image_path')
        ->assertTableColumnStateSet('name', 'Fruit', $fruit)
        ->assertTableColumnStateSet('products_count', 3, $fruit)
        ->assertTableColumnStateSet('products_count', 0, $empty);
});

it('creates an aisle with a tile and fills the slug from the name', function () {
    Livewire::test(CreateAisle::class)
        ->fillForm(['name' => 'Fresh Fruit'])
        ->assertFormSet(['slug' => 'fresh-fruit'])
        ->fillForm(['image_path' => tile()])
        ->call('create')
        ->assertHasNoFormErrors();

    $aisle = Category::query()->where('slug', 'fresh-fruit')->firstOrFail();
    expect($aisle->name)->toBe('Fresh Fruit')
        ->and($aisle->image_path)->toStartWith('aisles/');
    Storage::disk('public')->assertExists($aisle->image_path);
});

it('creates an aisle without a tile', function () {
    Livewire::test(CreateAisle::class)
        ->fillForm(['name' => 'Bakery', 'slug' => 'bakery'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Category::query()->where('slug', 'bakery')->value('image_path'))->toBeNull();
});

it('requires a unique name and slug and a tidy slug', function () {
    Category::factory()->create(['name' => 'Fruit', 'slug' => 'fruit']);

    Livewire::test(CreateAisle::class)
        ->fillForm(['name' => '', 'slug' => ''])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'slug' => 'required']);

    Livewire::test(CreateAisle::class)
        ->fillForm(['name' => 'Fruit', 'slug' => 'fruit'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique', 'slug' => 'unique']);

    Livewire::test(CreateAisle::class)
        ->fillForm(['name' => 'Other', 'slug' => 'Bad Slug'])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'regex']);
});

it('applies the product photo rules to the tile', function (Closure $file, string $message) {
    $component = Livewire::test(CreateAisle::class)
        ->fillForm(['name' => 'Fruit', 'slug' => 'fruit', 'image_path' => $file()])
        ->call('create');

    expect(implode(' ', tileErrors($component)))->toContain($message);
    expect(Category::query()->count())->toBe(0);
})->with([
    'not square' => [fn () => tile('a.jpg', 1400, 1200), 'square'],
    'too small' => [fn () => tile('a.jpg', 800, 800), '1200'],
    'wrong type' => [fn () => UploadedFile::fake()->image('a.png', 1200, 1200), 'JPG'],
]);

it('keeps the slug when the name is edited and allows the aisle its own name', function () {
    $aisle = Category::factory()->create(['name' => 'Fruit', 'slug' => 'fruit']);

    Livewire::test(EditAisle::class, ['record' => $aisle->getRouteKey()])
        ->assertFormSet(['name' => 'Fruit', 'slug' => 'fruit'])
        ->fillForm(['name' => 'Fruit and Veg'])
        ->assertFormSet(['slug' => 'fruit'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($aisle->refresh()->name)->toBe('Fruit and Veg')->and($aisle->slug)->toBe('fruit');
});

it('deletes the old tile file when it is replaced or removed', function () {
    Storage::disk('public')->put('aisles/old.jpg', 'x');
    $aisle = Category::factory()->create(['image_path' => 'aisles/old.jpg']);

    Livewire::test(EditAisle::class, ['record' => $aisle->getRouteKey()])
        ->fillForm(['image_path' => [tile('new.jpg')]])
        ->call('save')
        ->assertHasNoFormErrors();

    $aisle->refresh();
    expect($aisle->image_path)->not->toBe('aisles/old.jpg')->toStartWith('aisles/');
    Storage::disk('public')->assertMissing('aisles/old.jpg');
    Storage::disk('public')->assertExists($aisle->image_path);

    $current = $aisle->image_path;
    Livewire::test(EditAisle::class, ['record' => $aisle->getRouteKey()])
        ->fillForm(['image_path' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($aisle->refresh()->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($current);
});

it('leaves the tile alone when other fields change', function () {
    Storage::disk('public')->put('aisles/keep.jpg', 'x');
    $aisle = Category::factory()->create(['image_path' => 'aisles/keep.jpg']);

    Livewire::test(EditAisle::class, ['record' => $aisle->getRouteKey()])
        ->fillForm(['name' => 'Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($aisle->refresh()->image_path)->toBe('aisles/keep.jpg');
    Storage::disk('public')->assertExists('aisles/keep.jpg');
});

it('deletes an empty aisle from the edit page and the table, with its tile', function () {
    Storage::disk('public')->put('aisles/a.jpg', 'x');
    $first = Category::factory()->create(['image_path' => 'aisles/a.jpg']);
    $second = Category::factory()->create();

    Livewire::test(EditAisle::class, ['record' => $first->getRouteKey()])
        ->callAction(DeleteAction::class);

    expect(Category::query()->count())->toBe(1);
    Storage::disk('public')->assertMissing('aisles/a.jpg');

    Livewire::test(ListAisles::class)
        ->callAction(TestAction::make('delete')->table($second));

    expect(Category::query()->count())->toBe(0);
});

it('refuses to delete an aisle with products', function (int $count, string $text) {
    Storage::disk('public')->put('aisles/a.jpg', 'x');
    $aisle = Category::factory()->create(['image_path' => 'aisles/a.jpg']);
    Product::factory()->count($count)->for($aisle)->create();

    Livewire::test(EditAisle::class, ['record' => $aisle->getRouteKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified(Notification::make()->danger()->title($text));

    Livewire::test(ListAisles::class)
        ->callAction(TestAction::make('delete')->table($aisle))
        ->assertNotified(Notification::make()->danger()->title($text));

    expect(Category::query()->count())->toBe(1);
    Storage::disk('public')->assertExists('aisles/a.jpg');
})->with([
    'three' => [3, 'Move its 3 products to another aisle first.'],
    'one' => [1, 'Move its 1 product to another aisle first.'],
]);

it('counts products at the moment of deletion', function () {
    $aisle = Category::factory()->create();

    $component = Livewire::test(EditAisle::class, ['record' => $aisle->getRouteKey()]);
    Product::factory()->count(2)->for($aisle)->create();

    $component->callAction(DeleteAction::class)
        ->assertNotified(Notification::make()->danger()->title('Move its 2 products to another aisle first.'));

    expect(Category::query()->count())->toBe(1);
});

it('has no bulk delete', function () {
    expect(Livewire::test(ListAisles::class)->instance()->getTable()->getFlatBulkActions())->toBeEmpty();
});
