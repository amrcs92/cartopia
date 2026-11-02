<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('defaults products to visible', function () {
    expect(Product::factory()->create()->fresh()->is_visible)->toBeTrue()
        ->and(Product::factory()->hidden()->create()->fresh()->is_visible)->toBeFalse();
});

it('leaves hidden products out of the product list, searches and aisle filters', function () {
    $aisle = Category::factory()->create();
    Product::factory()->inCategory($aisle)->create(['name' => 'Shown lager']);
    Product::factory()->inCategory($aisle)->hidden()->create(['name' => 'Secret lager']);

    foreach ([[], ['q' => 'lager'], ['aisle' => [$aisle->slug]]] as $query) {
        $this->get(route('products.index', $query))
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Shown lager'));
    }
});

it('leaves hidden products out of the featured carousel', function () {
    Product::factory()->featured()->hidden()->create();
    $shown = Product::factory()->featured()->create();

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('featured', 1)
            ->where('featured.0.id', $shown->id));
});

it('leaves hidden products off the home price board', function () {
    Product::factory()->hidden()->create();
    $shown = Product::factory()->create();

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('counter', 1)
            ->where('counter.0.id', $shown->id));
});

it('counts only visible products in aisles and drops fully hidden aisles', function () {
    $mixed = Category::factory()->create();
    $hiddenOnly = Category::factory()->create();
    Product::factory()->inCategory($mixed)->create();
    Product::factory()->inCategory($mixed)->hidden()->create();
    Product::factory()->inCategory($hiddenOnly)->hidden()->create();

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('aisles', 1)
            ->where('aisles.0.slug', $mixed->slug)
            ->where('aisles.0.product_count', 1));

    $this->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page->has('aisles', 1));
});

it('404s the page of a hidden product', function () {
    $product = Product::factory()->hidden()->create();

    $this->get(route('products.show', $product))->assertNotFound();
});

it('refuses to add a hidden product to the basket', function () {
    $product = Product::factory()->hidden()->create();

    $this->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 1])
        ->assertNotFound();

    expect(session('cart'))->toBeNull();
});

it('still updates and removes a hidden product already in the basket', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $this->actingAs($user)->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 1]);
    $product->update(['is_visible' => false]);

    $this->patch(route('cart.items.update', $product), ['quantity' => 3])->assertRedirect();
    expect($user->cart->items()->pluck('quantity', 'product_id')->all())->toBe([$product->id => 3]);

    $this->delete(route('cart.items.destroy', $product))->assertRedirect();
    expect($user->cart->items()->count())->toBe(0);
});

it('drops hidden products from a guest basket merged at login', function () {
    $user = User::factory()->create();
    $shown = Product::factory()->create();
    $hidden = Product::factory()->create();
    $this->post(route('cart.items.store'), ['product_id' => $shown->id, 'quantity' => 1]);
    $this->post(route('cart.items.store'), ['product_id' => $hidden->id, 'quantity' => 2]);
    $hidden->update(['is_visible' => false]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect($user->cart()->first()->items()->pluck('quantity', 'product_id')->all())->toBe([$shown->id => 1]);
});
