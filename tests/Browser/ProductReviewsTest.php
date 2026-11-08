<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;

// The Reviews section on the product page: buyers post, edit and delete a
// review; everyone reads the written reviews five at a time.

function browserBuyer(Product $product): User
{
    $user = User::factory()->create(['name' => 'Amr Saleh']);
    $order = Order::factory()->for($user)->create();
    OrderItem::factory()->for($order)->create(['product_id' => $product->id]);

    return $user;
}

it('lets a buyer post, edit and delete a review', function () {
    $product = Product::factory()->create(['name' => 'Sparkling Water']);
    $this->actingAs(browserBuyer($product));

    visit('/products/'.$product->slug)
        ->assertSee('Reviews')
        ->assertSee('No ratings yet')
        ->assertButtonDisabled('Post review')
        ->click('[aria-label="4 stars"]')
        ->fill('#review-body', 'Crisp and very fresh.')
        ->assertSee('21/1000')
        ->press('Post review')
        ->waitForText('Thanks for your review!')
        ->assertSeeIn('[data-test="my-review"]', 'Crisp and very fresh.')
        ->assertSeeIn('[data-test="my-review"]', 'Amr S.')
        ->assertSee('4.0 from 1 rating')
        ->press('Edit')
        ->click('[aria-label="5 stars"]')
        ->press('Save')
        ->waitForText('Review updated.')
        ->assertSee('5.0 from 1 rating')
        ->press('Delete')
        ->assertSee('Delete your review?')
        ->click('[data-test="confirm-delete-review"]')
        ->waitForText('Review deleted.')
        ->assertSee('No ratings yet')
        ->assertButtonDisabled('Post review')
        ->assertNoJavaScriptErrors();

    expect(Review::count())->toBe(0);
});

it('tells a signed-in customer who has not bought it, and a guest, why they cannot review', function () {
    $product = Product::factory()->create();

    visit('/products/'.$product->slug)
        ->assertSee('to review this product.')
        ->assertDontSee('Post review');

    $this->actingAs(User::factory()->create());

    visit('/products/'.$product->slug)
        ->assertSee('Only customers who bought this product can review it.')
        ->assertDontSee('Post review');
});

it('shows five written reviews and loads more on request', function () {
    $product = Product::factory()->create();
    foreach (range(1, 7) as $i) {
        Review::factory()->for($product)->withBody("Review number {$i}")->create(['created_at' => now()->subMinutes($i)]);
    }

    visit('/products/'.$product->slug)
        ->assertSee('Review number 5')
        ->assertDontSee('Review number 6')
        ->press('Show more reviews')
        ->waitForText('Review number 7')
        ->assertPathIs('/products/'.$product->slug)
        ->assertDontSee('Show more reviews');
});

it('wraps a long review without scrolling sideways on a phone', function () {
    $product = Product::factory()->create();
    Review::factory()->for($product)->withBody(str_repeat('x', 1000))->create();

    visit('/products/'.$product->slug)->resize(360, 800)
        ->assertSee('xxxxxxxxxx')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
});
