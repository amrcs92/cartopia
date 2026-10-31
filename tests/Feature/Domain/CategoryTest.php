<?php

use App\Models\Category;
use App\Models\Product;
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
