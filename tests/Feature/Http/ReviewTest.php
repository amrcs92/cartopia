<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/** A customer with a placed order holding the product. */
function buyerOf(Product $product): User
{
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();
    OrderItem::factory()->for($order)->create(['product_id' => $product->id]);

    return $user;
}

function toastAfter(mixed $test, User $user, Product $product): mixed
{
    return $test->actingAs($user)->get(route('products.show', $product));
}

it('lets a buyer post, update and delete their review', function () {
    $product = Product::factory()->create();
    $buyer = buyerOf($product);

    $this->actingAs($buyer)
        ->from(route('products.show', $product))
        ->put(route('products.review.update', $product), ['stars' => 4, 'body' => 'Crisp and fresh.'])
        ->assertRedirect(route('products.show', $product));

    toastAfter($this, $buyer, $product)->assertInertia(fn (Assert $page) => $page
        ->hasFlash('toast', ['type' => 'success', 'message' => 'Thanks for your review!']));

    $review = Review::sole();
    expect($review->stars)->toBe(4)->and($review->body)->toBe('Crisp and fresh.');

    $this->actingAs($buyer)->put(route('products.review.update', $product), ['stars' => 5, 'body' => 'Even better.']);
    toastAfter($this, $buyer, $product)->assertInertia(fn (Assert $page) => $page
        ->hasFlash('toast', ['type' => 'success', 'message' => 'Review updated.']));

    expect(Review::sole()->only(['stars', 'body']))->toBe(['stars' => 5, 'body' => 'Even better.']);

    $this->actingAs($buyer)->delete(route('products.review.destroy', $product))->assertRedirect();
    toastAfter($this, $buyer, $product)->assertInertia(fn (Assert $page) => $page
        ->hasFlash('toast', ['type' => 'success', 'message' => 'Review deleted.']));

    expect(Review::count())->toBe(0);
});

it('refuses customers who have not bought the product', function () {
    $product = Product::factory()->create();
    $stranger = User::factory()->create();
    Review::factory()->for($product)->for($stranger)->create();

    $this->actingAs($stranger)->put(route('products.review.update', $product), ['stars' => 5])->assertForbidden();
    $this->actingAs($stranger)->delete(route('products.review.destroy', $product))->assertForbidden();

    expect(Review::sole()->user_id)->toBe($stranger->id);
});

it('sends guests to log in', function () {
    $product = Product::factory()->create();

    $this->put(route('products.review.update', $product), ['stars' => 3])->assertRedirect(route('login'));
    $this->delete(route('products.review.destroy', $product))->assertRedirect(route('login'));
});

it('404s a review for a hidden product', function () {
    $product = Product::factory()->hidden()->create();
    $buyer = buyerOf($product);

    $this->actingAs($buyer)->put(route('products.review.update', $product), ['stars' => 5])->assertNotFound();
    $this->actingAs($buyer)->delete(route('products.review.destroy', $product))->assertNotFound();
});

it('validates the stars', function (mixed $stars) {
    $product = Product::factory()->create();

    $this->actingAs(buyerOf($product))
        ->put(route('products.review.update', $product), ['stars' => $stars])
        ->assertSessionHasErrors('stars');
})->with([0, 6, 'abc', null, 4.5]);

it('caps the text at 1,000 characters', function () {
    $product = Product::factory()->create();
    $buyer = buyerOf($product);

    $this->actingAs($buyer)
        ->put(route('products.review.update', $product), ['stars' => 3, 'body' => str_repeat('a', 1001)])
        ->assertSessionHasErrors('body');

    $this->actingAs($buyer)
        ->put(route('products.review.update', $product), ['stars' => 3, 'body' => str_repeat('a', 1000)])
        ->assertSessionHasNoErrors();
});

it('trims the text and stores blank text as none', function (string $body, ?string $stored) {
    $product = Product::factory()->create();

    $this->actingAs(buyerOf($product))
        ->put(route('products.review.update', $product), ['stars' => 3, 'body' => $body])
        ->assertSessionHasNoErrors();

    expect(Review::sole()->body)->toBe($stored);
})->with([
    ['  hi  ', 'hi'],
    ['   ', null],
    ['', null],
]);

it('keeps a hidden review hidden when its author edits it', function () {
    $product = Product::factory()->create();
    $buyer = buyerOf($product);
    $review = Review::factory()->for($product)->for($buyer)->hidden()->create();

    $this->actingAs($buyer)->put(route('products.review.update', $product), ['stars' => 2, 'body' => 'Changed']);

    expect($review->fresh()->hidden_at)->not->toBeNull()
        ->and($review->fresh()->body)->toBe('Changed');
});

it('no longer has the old rating route', function () {
    expect(Route::has('products.rating.update'))->toBeFalse();
});
