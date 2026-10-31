<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

it('seeds the legacy catalog, shipping, demo and admin users', function () {
    Storage::fake('public');

    $this->seed();

    $expected = [
        'apple' => [30, 'apple.jpg'],
        'beer' => [200, 'heineken_beer.jpg'],
        'water' => [100, 'water_bottle.jpg'],
        'cheese-triangle' => [374, 'cheese-traingles.jpg'],
    ];

    expect(Product::query()->orderBy('id')->pluck('slug')->all())->toBe(array_keys($expected));

    foreach ($expected as $slug => [$cents, $file]) {
        $product = Product::query()->where('slug', $slug)->with('images')->firstOrFail();
        expect($product->price->cents())->toBe($cents)
            ->and($product->description)->not->toBe('')
            ->and($product->images)->toHaveCount(1)
            ->and($product->images[0]->path)->toBe("products/{$file}")
            ->and($product->images[0]->sort_order)->toBe(0);
        Storage::disk('public')->assertExists("products/{$file}");
    }

    $aisles = Category::query()->orderBy('id')->get()
        ->mapWithKeys(fn ($c) => [$c->slug => [$c->name, $c->products()->orderBy('id')->pluck('slug')->all()]])
        ->all();
    expect($aisles)->toBe([
        'fruit-and-veg' => ['Fruit and veg', ['apple']],
        'drinks' => ['Drinks', ['beer', 'water']],
        'dairy' => ['Dairy', ['cheese-triangle']],
    ])->and(Product::query()->where('is_featured', false)->count())->toBe(0);

    expect(Product::query()->where('slug', 'water')->value('description'))->toContain("St Ann's Spring")
        ->and(Product::query()->where('slug', 'apple')->value('description'))->toContain('"health is wealth."')
        ->and(ShippingMethod::query()->count())->toBe(2);

    $demo = User::query()->where('email', 'amrcs1992@gmail.com')->firstOrFail();
    expect($demo->name)->toBe('amrcs92')
        ->and(Hash::check('12345', $demo->password))->toBeTrue()
        ->and($demo->balance->cents())->toBe(10000)
        ->and($demo->walletTransactions()->where('type', 'signup_bonus')->count())->toBe(1);

    $admin = User::query()->where('email', 'admin@cartopia.test')->firstOrFail();
    expect($admin->is_admin)->toBeTrue()
        ->and($admin->balance->cents())->toBe(0);
});
