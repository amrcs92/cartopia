<?php

use App\Catalog\Aisles;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('keeps products when their aisle is deleted', function () {
    $aisle = Category::factory()->create();
    $product = Product::factory()->inCategory($aisle)->create();

    $aisle->delete();

    expect($product->fresh()->category_id)->toBeNull();
});

it('uses the aisle image, then the newest product photo, then nothing', function () {
    Storage::fake('public');
    $aisle = Category::factory()->create(['image_path' => null]);
    expect($aisle->imageUrl())->toBeNull();

    Product::factory()->inCategory($aisle)->withImages()->create();
    $newest = Product::factory()->inCategory($aisle)->withImages()->create();
    expect($aisle->fresh()->imageUrl())
        ->toBe(Storage::disk('public')->url($newest->primaryImage->path));

    $aisle->update(['image_path' => 'categories/drinks.jpg']);
    expect($aisle->fresh()->imageUrl())->toBe(Storage::disk('public')->url('categories/drinks.jpg'));
});

it('lists aisles with their images in a fixed number of queries', function () {
    Storage::fake('public');

    $count = function (int $aisles): int {
        Category::query()->delete();
        Category::factory()->count($aisles)->create(['image_path' => null])
            ->each(fn (Category $aisle) => Product::factory()->inCategory($aisle)->withImages()->create());

        DB::enableQueryLog();
        Aisles::all()->each->imageUrl();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $queries;
    };

    expect($count(6))->toBe($count(2));
});
