<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

it('seeds the legacy catalog, shipping, demo and admin users', function () {
    fakePublicDisk();

    $this->seed();

    $expected = [
        'apple' => [30, 'apple.jpg'],
        'monster-energy-drink' => [200, 'monster-energy-drink.png'],
        'water' => [100, 'water_bottle.jpg'],
        'cheese-triangle' => [374, 'cheese-traingles.jpg'],
    ];

    expect(Product::query()->orderBy('id')->limit(4)->pluck('slug')->all())->toBe(array_keys($expected));

    foreach ($expected as $slug => [$cents, $file]) {
        $product = Product::query()->where('slug', $slug)->with('images')->firstOrFail();
        expect($product->price->cents())->toBe($cents)
            ->and($product->description)->not->toBe('')
            ->and($product->images)->toHaveCount(1)
            ->and($product->images[0]->path)->toBe("products/{$file}")
            ->and($product->images[0]->sort_order)->toBe(0);
        Storage::disk('public')->assertExists("products/{$file}");
    }

    expect(Product::query()->count())->toBe(24)
        ->and(Category::query()->orderBy('id')->pluck('name', 'slug')->all())->toBe([
            'fruit-and-veg' => 'Fruit and veg',
            'drinks' => 'Drinks',
            'dairy' => 'Dairy',
            'bakery' => 'Bakery',
            'pantry' => 'Pantry',
            'snacks' => 'Snacks',
        ])
        ->and(Product::query()->where('is_featured', false)->count())->toBe(0)
        ->and(Product::query()->distinct()->count('sku'))->toBe(24)
        ->and(Product::query()->where('slug', 'olive-oil')->firstOrFail()->price->cents())->toBe(649)
        ->and(Category::query()->where('slug', 'drinks')->firstOrFail()->products()->orderBy('id')->limit(2)->pluck('slug')->all())
        ->toBe(['monster-energy-drink', 'water']);

    Category::query()->withCount('products')->get()
        ->each(fn (Category $aisle) => expect($aisle->products_count)->toBe(4));

    Product::query()->with('images')->get()->each(function (Product $product) {
        expect($product->images)->toHaveCount(1)
            ->and($product->description)->toMatch('/\w.*\.\s+\w/')
            ->and(strlen($product->sku))->toBe(14);
        Storage::disk('public')->assertExists($product->images[0]->path);
    });

    expect(Product::query()->where('slug', 'water')->value('description'))->toContain("St Ann's Spring")
        ->and(Product::query()->where('slug', 'monster-energy-drink')->value('short_name'))->toBe('Monster Energy')
        ->and(Product::query()->where('slug', 'apple')->value('short_name'))->toBeNull()
        ->and(Product::query()->where('slug', 'monster-energy-drink')->value('description'))->toContain('160 mg of caffeine')
        ->and(Product::query()->where('slug', 'apple')->value('description'))->toContain('"health is wealth."')
        ->and(ShippingMethod::query()->count())->toBe(2);

    $demo = User::query()->where('email', 'demo@cartopia.test')->firstOrFail();
    expect($demo->name)->toBe('Sam Rivera')
        ->and(Hash::check('12345', $demo->password))->toBeTrue()
        ->and($demo->balance->cents())->toBe(2000)
        ->and($demo->walletTransactions()->where('type', 'signup_bonus')->count())->toBe(1)
        ->and(User::query()->where('email', 'amrcs1992@gmail.com')->exists())->toBeFalse();

    $admin = User::query()->where('email', 'admin@cartopia.test')->firstOrFail();
    expect($admin->is_admin)->toBeTrue()
        ->and($admin->balance->cents())->toBe(0);
});
