<?php

use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('adds to a guest session cart', function () {
    $beer = Product::factory()->create(['price' => 200]);

    $this->from(route('products.index'))
        ->post(route('cart.items.store'), ['product_id' => $beer->id, 'quantity' => 2])
        ->assertRedirect(route('products.index'))
        ->assertSessionMissing('success');

    expect(session('cart'))->toBe([$beer->id => 2]);

    $this->get(route('cart.show'))
        ->assertInertia(fn (Assert $page) => $page->where('cart.count', 2));
});

it('adds to a user database cart', function () {
    $user = User::factory()->create();
    $beer = Product::factory()->create();

    $this->actingAs($user)
        ->post(route('cart.items.store'), ['product_id' => $beer->id, 'quantity' => 3])
        ->assertRedirect();

    expect($user->cart->items()->pluck('quantity', 'product_id')->all())->toBe([$beer->id => 3])
        ->and(session('cart'))->toBeNull();
});

it('validates cart input', function (array $payload, string $field) {
    Product::factory()->create();

    $this->post(route('cart.items.store'), $payload)->assertSessionHasErrors($field);
})->with([
    'zero quantity' => [fn () => ['product_id' => 1, 'quantity' => 0], 'quantity'],
    'too many' => [fn () => ['product_id' => 1, 'quantity' => 100], 'quantity'],
    'non numeric' => [fn () => ['product_id' => 1, 'quantity' => 'x'], 'quantity'],
    'unknown product' => [fn () => ['product_id' => 999, 'quantity' => 1], 'product_id'],
]);

it('updates and removes lines', function () {
    $beer = Product::factory()->create();
    $this->post(route('cart.items.store'), ['product_id' => $beer->id, 'quantity' => 1]);

    $this->from(route('cart.show'))
        ->patch(route('cart.items.update', $beer->id), ['quantity' => 5])
        ->assertRedirect(route('cart.show'));
    expect(session('cart'))->toBe([$beer->id => 5]);

    $this->patch(route('cart.items.update', $beer->id), ['quantity' => 100])->assertSessionHasErrors('quantity');

    $this->delete(route('cart.items.destroy', $beer->id))->assertRedirect();
    expect(session('cart'))->toBe([]);
});

it('returns 404 when updating or removing a line not in the cart', function () {
    $beer = Product::factory()->create();

    $this->patch(route('cart.items.update', $beer->id), ['quantity' => 2])->assertNotFound();
    $this->delete(route('cart.items.destroy', $beer->id))->assertNotFound();
});

it('renders server-computed totals', function () {
    $beer = Product::factory()->create(['price' => 200]);
    $water = Product::factory()->create(['price' => 100]);
    ShippingMethod::factory()->pickup()->create();
    ShippingMethod::factory()->ups()->create();

    $this->post(route('cart.items.store'), ['product_id' => $beer->id, 'quantity' => 2]);
    $this->post(route('cart.items.store'), ['product_id' => $water->id, 'quantity' => 2]);

    $this->get(route('cart.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cart/CartPage')
            ->where('cart.subtotal', '$6.00')
            ->where('cart.total_quantity', 4)
            ->where('cart.count', 4)
            ->has('cart.lines', 2)
            ->where('cart.lines.0.product.id', $beer->id)
            ->where('cart.lines.0.quantity', 2)
            ->where('cart.lines.0.unit_price', '$2.00')
            ->where('cart.lines.0.line_total', '$4.00')
            ->has('shippingMethods', 2)
            ->where('shippingMethods.1.name', 'UPS')
            ->where('shippingMethods.1.fee', '$5.00')
        );
});

it('merges the guest cart on login', function () {
    $user = User::factory()->create();
    $beer = Product::factory()->create();
    $this->post(route('cart.items.store'), ['product_id' => $beer->id, 'quantity' => 2]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect($user->cart->items()->pluck('quantity', 'product_id')->all())->toBe([$beer->id => 2]);
});

it('keeps the guest cart when a guest registers', function () {
    $beer = Product::factory()->create();
    $this->post(route('cart.items.store'), ['product_id' => $beer->id, 'quantity' => 2]);

    $this->post(route('register.store'), [
        'name' => 'New Person',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'new@example.com')->firstOrFail();
    expect($user->cart->items()->pluck('quantity', 'product_id')->all())->toBe([$beer->id => 2]);
});

it('flashes what was just added, with the basket summary', function () {
    $apple = Product::factory()->create(['price' => 30]);

    $this->post(route('cart.items.store'), ['product_id' => $apple->id, 'quantity' => 2]);

    $this->get(route('products.index'))->assertInertia(fn (Assert $page) => $page
        ->where('flash.addedToBasket.product.id', $apple->id)
        ->where('flash.addedToBasket.quantity', 2)
        ->where('flash.addedToBasket.line_total', '$0.60')
        ->where('flash.addedToBasket.basket.count', 2)
        ->where('flash.addedToBasket.basket.subtotal', '$0.60')
    );
});

it('suggests at most two featured products that are not already in the basket', function () {
    $featured = Product::factory()->featured()->count(3)->create();
    $added = $featured->first();

    $this->post(route('cart.items.store'), ['product_id' => $added->id, 'quantity' => 1]);

    $this->get(route('products.index'))->assertInertia(fn (Assert $page) => $page
        ->has('flash.addedToBasket.suggestions', 2)
        ->where('flash.addedToBasket.suggestions.0.id', fn (int $id) => $id !== $added->id)
        ->where('flash.addedToBasket.suggestions.1.id', fn (int $id) => $id !== $added->id)
    );
});

it('suggests nothing when every featured product is already in the basket', function () {
    $featured = Product::factory()->featured()->count(2)->create();

    foreach ($featured as $product) {
        $this->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 1]);
    }

    $this->get(route('products.index'))->assertInertia(fn (Assert $page) => $page
        ->has('flash.addedToBasket.suggestions', 0)
    );
});

it('does not repeat the panel on the next page', function () {
    $apple = Product::factory()->create();

    $this->post(route('cart.items.store'), ['product_id' => $apple->id, 'quantity' => 1]);
    $this->get(route('products.index'));

    $this->get(route('products.index'))->assertInertia(fn (Assert $page) => $page
        ->where('flash.addedToBasket', null)
    );
});

it('sends the featured products for the empty basket', function () {
    $featured = Product::factory()->featured()->create();
    Product::factory()->create();

    $this->get(route('cart.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cart/CartPage')
            ->has('cart.lines', 0)
            ->has('featured', 1)
            ->where('featured.0.id', $featured->id)
        );
});
