<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function placedOrderFor(User $user, Product $product): void
{
    $order = Order::factory()->for($user)->create();
    OrderItem::factory()->for($order)->create(['product_id' => $product->id]);
}

it('tells guests, non-buyers and buyers whether they can review', function () {
    $product = Product::factory()->create();
    $buyer = User::factory()->create();
    placedOrderFor($buyer, $product);

    $this->get(route('products.show', $product))
        ->assertInertia(fn (Assert $page) => $page->where('reviews.can_review', 'guest')->where('reviews.mine', null));

    $this->actingAs(User::factory()->create())->get(route('products.show', $product))
        ->assertInertia(fn (Assert $page) => $page->where('reviews.can_review', 'not_buyer'));

    $this->actingAs($buyer)->get(route('products.show', $product))
        ->assertInertia(fn (Assert $page) => $page->where('reviews.can_review', 'yes')->where('reviews.mine', null));
});

it("gives the author their own review, even when it's hidden", function () {
    Carbon::setTestNow('2026-11-08 10:00:00');
    $product = Product::factory()->create();
    $buyer = User::factory()->create(['name' => 'Amr Saleh']);
    placedOrderFor($buyer, $product);
    $review = Review::factory()->for($product)->for($buyer)->hidden()->withBody("Good.\nWould buy again.")->create(['stars' => 4]);

    $this->actingAs($buyer)->get(route('products.show', $product))
        ->assertInertia(fn (Assert $page) => $page->where('reviews.mine', [
            'id' => $review->id,
            'author' => 'Amr S.',
            'stars' => 4,
            'body' => "Good.\nWould buy again.",
            'date' => '8 Nov 2026',
            'edited' => false,
        ]));
});

it('lists visible written reviews, newest first, without stars-only or the viewer\'s own', function () {
    $product = Product::factory()->create();
    $viewer = User::factory()->create();
    placedOrderFor($viewer, $product);

    $old = Review::factory()->for($product)->withBody('Old')->create(['created_at' => now()->subDays(3)]);
    $new = Review::factory()->for($product)->withBody('New')->create(['created_at' => now()->subDay()]);
    Review::factory()->for($product)->create();
    Review::factory()->for($product)->hidden()->withBody('Hidden')->create();
    Review::factory()->for($product)->for($viewer)->withBody('Mine')->create();
    Review::factory()->withBody('Other product')->create();

    $this->actingAs($viewer)->get(route('products.show', $product))
        ->assertInertia(fn (Assert $page) => $page
            ->where('reviewList.data.0.id', $new->id)
            ->where('reviewList.data.1.id', $old->id)
            ->has('reviewList.data', 2));
});

it('pages the written reviews five at a time', function () {
    $product = Product::factory()->create();
    foreach (range(1, 7) as $i) {
        Review::factory()->for($product)->withBody("Review {$i}")->create(['created_at' => now()->subMinutes($i)]);
    }

    $this->get(route('products.show', $product))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reviewList.data', 5)
            ->where('reviewList.data.0.body', 'Review 1'));

    $this->get(route('products.show', [$product, 'reviews_page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reviewList.data', 2)
            ->where('reviewList.data.0.body', 'Review 6'));
});

it('shortens the author name to a first name and last initial', function (string $name, string $author) {
    $product = Product::factory()->create();
    Review::factory()->for($product)->for(User::factory()->create(['name' => $name]))->withBody('Nice')->create();

    $this->get(route('products.show', $product))
        ->assertInertia(fn (Assert $page) => $page->where('reviewList.data.0.author', $author));
})->with([
    ['Amr Saleh', 'Amr S.'],
    ['Cher', 'Cher'],
    ['  ada   lovelace ', 'Ada L.'],
    ['José van der Berg', 'José B.'],
]);

it('marks a review edited only after a real edit', function () {
    $product = Product::factory()->create();
    $review = Review::factory()->for($product)->withBody('First')->create();

    $this->get(route('products.show', $product))
        ->assertInertia(fn (Assert $page) => $page->where('reviewList.data.0.edited', false));

    $this->travel(5)->minutes();
    $review->update(['body' => 'Second']);

    $this->get(route('products.show', $product))
        ->assertInertia(fn (Assert $page) => $page->where('reviewList.data.0.edited', true));
});
