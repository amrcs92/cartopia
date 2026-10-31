<?php

use App\Enums\WalletTransactionType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Rating;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\Money;
use Illuminate\Database\QueryException;

it('casts money columns to Money', function () {
    $product = Product::factory()->create(['price' => 374])->fresh();

    expect($product->price)->toBeInstanceOf(Money::class)
        ->and($product->price->equals(Money::fromCents(374)))->toBeTrue();

    $product->price = Money::fromCents(99);
    $product->save();

    expect($product->getRawOriginal('price'))->toBe(99);
});

it('allows one rating per user per product', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    Rating::create(['user_id' => $user->id, 'product_id' => $product->id, 'stars' => 4]);
    Rating::create(['user_id' => $user->id, 'product_id' => $product->id, 'stars' => 5]);
})->throws(QueryException::class);

it('allows one cart line per product', function () {
    $cart = Cart::factory()->create();
    $product = Product::factory()->create();

    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2]);
})->throws(QueryException::class);

it('keeps order item snapshots when a product is deleted', function () {
    $product = Product::factory()->create();
    $item = OrderItem::factory()->for(Order::factory())->create([
        'product_id' => $product->id,
        'product_name' => 'Snapshot name',
    ]);

    $product->delete();
    $item->refresh();

    expect($item->product_id)->toBeNull()
        ->and($item->product_name)->toBe('Snapshot name');
});

it('picks the lowest sort_order image as primary', function () {
    $product = Product::factory()->create();
    ProductImage::factory()->for($product)->create(['sort_order' => 2]);
    ProductImage::factory()->for($product)->create(['sort_order' => 0]);

    expect($product->primaryImage->sort_order)->toBe(0)
        ->and($product->images->pluck('sort_order')->all())->toBe([0, 2]);
});

it('defaults new users to zero balance and non-admin', function () {
    $user = User::factory()->create()->fresh();

    expect($user->balance->cents())->toBe(0)
        ->and($user->is_admin)->toBeFalse()
        ->and(User::factory()->admin()->create()->is_admin)->toBeTrue();
});

it('round-trips negative cents on wallet transactions', function () {
    $user = User::factory()->create();

    $tx = WalletTransaction::create([
        'user_id' => $user->id,
        'type' => WalletTransactionType::Purchase,
        'amount' => Money::fromCents(-1250),
        'balance_after' => 0,
    ])->fresh();

    expect($tx->amount->cents())->toBe(-1250)
        ->and($tx->type)->toBe(WalletTransactionType::Purchase)
        ->and($tx->type->label())->toBe('Purchase')
        ->and($tx->created_at)->not->toBeNull();
});
