<?php

use App\Cart\Cart;
use App\Enums\WalletTransactionType;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\Money;

// Every store page, at the tablet and desktop widths, must fit the window with
// no sideways scroll. The phone width is covered by the 360px tests.

/**
 * Seeds what the page needs and returns its path. The shopper is signed in on
 * every page, so the header and the pages behind a login look as they do live.
 */
function seedPage(string $page): string
{
    $user = User::factory()->withBalance(Money::fromCents(50000))->create();
    test()->actingAs($user);

    $order = null;
    $fruit = Category::factory()->create(['name' => 'Fruit and veg']);
    $drinks = Category::factory()->create(['name' => 'Drinks']);
    Product::factory()->count(5)->featured()->withImages()->inCategory($fruit)->create();
    $product = Product::factory()->withImages()->inCategory($drinks)->create(['name' => 'Sparkling Water']);
    Product::factory()->count(8)->inCategory($fruit)->create();
    ShippingMethod::factory()->pickup()->create();
    ShippingMethod::factory()->ups()->create();

    switch ($page) {
        case 'basket':
            Cart::forUser($user)->add($product, 2);
            break;
        case 'orders':
        case 'receipt':
            $order = Order::factory()->for($user)->create();
            OrderItem::factory()->for($order)->create(['product_id' => $product->id]);
            OrderItem::factory()->for($order)->create(['product_id' => Product::factory()->create()->id]);
            break;
        case 'wallet':
            foreach (range(1, 5) as $i) {
                WalletTransaction::create([
                    'user_id' => $user->id,
                    'type' => WalletTransactionType::AdminAdjustment,
                    'amount' => Money::fromCents(1000 * $i),
                    'balance_after' => Money::fromCents(50000 + 1000 * $i),
                    'description' => 'Goodwill credit number '.$i,
                ]);
            }
            break;
    }

    return match ($page) {
        'home' => '/',
        'shop' => '/products',
        'product' => "/products/{$product->slug}",
        'basket' => '/cart',
        'orders' => '/orders',
        'receipt' => "/orders/{$order?->id}",
        'wallet' => '/wallet',
    };
}

it('fits the window with no sideways scroll', function (int $width, string $page) {
    visit(seedPage($page))->resize($width, 900)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
})->with([768, 1024, 1280, 1440, 1920])
    ->with(['home', 'shop', 'product', 'basket', 'orders', 'receipt', 'wallet']);
