<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;

function orderHolding(User $user, Product $product, OrderStatus $status = OrderStatus::Placed): Order
{
    $order = Order::factory()->for($user)->create(['status' => $status]);
    OrderItem::factory()->for($order)->create(['product_id' => $product->id]);

    return $order;
}

it('lets a customer review a product from one of their placed orders', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    orderHolding($user, $product);

    expect(Review::canReview($user, $product))->toBeTrue();
});

it('does not count orders that are not placed', function (OrderStatus $status) {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    orderHolding($user, $product, $status);

    expect(Review::canReview($user, $product))->toBeFalse();
})->with([OrderStatus::AwaitingPayment, OrderStatus::Cancelled, OrderStatus::Refunded]);

it("does not count someone else's order or another product", function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    orderHolding(User::factory()->create(), $product);
    orderHolding($user, Product::factory()->create());

    expect(Review::canReview($user, $product))->toBeFalse();
});

it('does not count an order item whose product was deleted', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->for($user)->create();
    OrderItem::factory()->for($order)->create(['product_id' => null]);

    expect(Review::canReview($user, $product))->toBeFalse();
});

it('deletes the reviews of people who never bought the product', function () {
    $product = Product::factory()->create();
    $buyer = User::factory()->create();
    $refunded = User::factory()->create();
    $stranger = User::factory()->create();
    orderHolding($buyer, $product);
    orderHolding($refunded, $product, OrderStatus::Refunded);

    $kept = Review::factory()->for($product)->for($buyer)->create();
    Review::factory()->for($product)->for($refunded)->create();
    Review::factory()->for($product)->for($stranger)->create();

    expect(Review::deleteIneligible())->toBe(2)
        ->and(Review::query()->pluck('id')->all())->toBe([$kept->id]);
});

it('counts a review as edited only when it changed over a minute after posting', function () {
    $review = Review::factory()->create();

    expect($review->isEdited())->toBeFalse();

    $this->travel(30)->seconds();
    $review->update(['stars' => 2]);
    expect($review->isEdited())->toBeFalse();

    $this->travel(2)->minutes();
    $review->update(['stars' => 3]);
    expect($review->isEdited())->toBeTrue();
});
