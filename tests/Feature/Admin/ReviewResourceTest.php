<?php

use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('lists reviews with product, customer, stars, text and a Hidden badge', function () {
    $product = Product::factory()->create(['name' => 'Sparkling Water']);
    $shown = Review::factory()->for($product)->for(User::factory()->create(['name' => 'Sam Shopper']))
        ->withBody('Crisp and fresh.')->create(['stars' => 4]);
    $hidden = Review::factory()->for($product)->hidden()->create();

    Livewire::test(ListReviews::class)
        ->assertCanSeeTableRecords([$shown, $hidden])
        ->assertTableColumnStateSet('product.name', 'Sparkling Water', $shown)
        ->assertTableColumnStateSet('user.name', 'Sam Shopper', $shown)
        ->assertTableColumnStateSet('stars', 4, $shown)
        ->assertTableColumnStateSet('body', 'Crisp and fresh.', $shown)
        ->assertTableColumnStateSet('hidden', 'Hidden', $hidden)
        ->assertTableColumnStateSet('hidden', null, $shown)
        ->assertTableColumnExists('created_at');
});

it('filters by hidden or visible and by product', function () {
    $water = Product::factory()->create();
    $shown = Review::factory()->for($water)->create();
    $hidden = Review::factory()->for($water)->hidden()->create();
    $other = Review::factory()->create();

    Livewire::test(ListReviews::class)
        ->filterTable('visibility', 'hidden')
        ->assertCanSeeTableRecords([$hidden])
        ->assertCanNotSeeTableRecords([$shown, $other])
        ->resetTableFilters()
        ->filterTable('visibility', 'visible')
        ->assertCanSeeTableRecords([$shown, $other])
        ->assertCanNotSeeTableRecords([$hidden])
        ->resetTableFilters()
        ->filterTable('product', $water->id)
        ->assertCanSeeTableRecords([$shown, $hidden])
        ->assertCanNotSeeTableRecords([$other]);
});

it('searches the text and the customer name', function () {
    $byText = Review::factory()->withBody('Too fizzy for me')->create();
    $byName = Review::factory()->for(User::factory()->create(['name' => 'Lina Hassan']))->create();
    $neither = Review::factory()->withBody('Fine')->create();

    Livewire::test(ListReviews::class)
        ->searchTable('fizzy')
        ->assertCanSeeTableRecords([$byText])
        ->assertCanNotSeeTableRecords([$byName, $neither])
        ->searchTable('Lina')
        ->assertCanSeeTableRecords([$byName])
        ->assertCanNotSeeTableRecords([$byText, $neither]);
});

it('hides and unhides a review without marking it edited', function () {
    $review = Review::factory()->withBody('Rude words')->create();
    $updatedAt = $review->fresh()->updated_at;

    $this->travel(10)->minutes();

    Livewire::test(ListReviews::class)
        ->callTableAction('hide', $review)
        ->assertNotified('Review hidden');

    expect($review->fresh()->hidden_at)->not->toBeNull()
        ->and($review->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();

    Livewire::test(ListReviews::class)
        ->assertTableActionHidden('hide', $review)
        ->callTableAction('unhide', $review)
        ->assertNotified('Review shown again');

    expect($review->fresh()->hidden_at)->toBeNull()
        ->and($review->fresh()->updated_at->equalTo($updatedAt))->toBeTrue()
        ->and($review->fresh()->isEdited())->toBeFalse();
});

it('deletes a review after confirming', function () {
    $review = Review::factory()->create();

    Livewire::test(ListReviews::class)
        ->callTableAction('delete', $review);

    expect(Review::count())->toBe(0);
});

it('has no form to create or edit a review', function () {
    expect(ReviewResource::canCreate())->toBeFalse()
        ->and(ReviewResource::hasPage('edit'))->toBeFalse();
});
