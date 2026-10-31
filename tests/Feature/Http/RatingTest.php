<?php

use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it("saves the viewer's rating and updates the average", function () {
    $product = Product::factory()->create();
    $other = User::factory()->create();
    $viewer = User::factory()->create();

    $this->actingAs($other)->put(route('products.rating.update', $product), ['stars' => 4])->assertRedirect();
    $this->actingAs($viewer)
        ->from(route('products.show', $product))
        ->put(route('products.rating.update', $product), ['stars' => 5])
        ->assertRedirect(route('products.show', $product))
        ->assertSessionHas('success', 'Thanks for rating!');

    $this->actingAs($viewer)->get(route('products.show', $product))
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.rating_average', 4.5)
            ->where('product.rating_count', 2)
            ->where('product.my_rating', 5));
});

it('validates stars', function (mixed $stars) {
    $product = Product::factory()->create();

    $this->actingAs(User::factory()->create())
        ->put(route('products.rating.update', $product), ['stars' => $stars])
        ->assertSessionHasErrors('stars');
})->with([0, 6, 'abc']);

it('redirects guests to login', function () {
    $product = Product::factory()->create();

    $this->put(route('products.rating.update', $product), ['stars' => 3])
        ->assertRedirect(route('login'));
});
