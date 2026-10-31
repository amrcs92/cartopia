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
        ->assertSessionHas('success', 'Added to cart.');

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
