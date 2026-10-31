<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CartItemController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CheckoutStartController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\TopUpController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('products', [ProductController::class, 'index'])->name('products.index');
Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');

Route::put('products/{product}/rating', [RatingController::class, 'update'])
    ->middleware('auth')
    ->name('products.rating.update');

Route::get('cart', [CartController::class, 'show'])->name('cart.show');
Route::post('cart/items', [CartItemController::class, 'store'])->name('cart.items.store');
Route::patch('cart/items/{product:id}', [CartItemController::class, 'update'])->name('cart.items.update');
Route::delete('cart/items/{product:id}', [CartItemController::class, 'destroy'])->name('cart.items.destroy');

Route::get('checkout/start', CheckoutStartController::class)
    ->middleware('auth')
    ->name('checkout.start');

Route::post('checkout', [CheckoutController::class, 'store'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('checkout.store');

Route::middleware('auth')->group(function () {
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])
        ->middleware('can:view,order')
        ->name('orders.show');

    Route::get('wallet', [WalletController::class, 'show'])->name('wallet.show');
    Route::post('wallet/top-ups', [TopUpController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('wallet.top-ups.store');
});

require __DIR__.'/settings.php';

// Unmatched URLs never reach the web middleware group, so they would render
// without the shared auth, basket and flash props. A fallback route runs them.
// Aborting here sends it through the 404 hook in bootstrap/app.php, which
// renders the Inertia page, or JSON for requests that expect JSON.
Route::fallback(fn () => abort(404));
