<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CartItemController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('products', [ProductController::class, 'index'])->name('products.index');
Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');

Route::get('cart', [CartController::class, 'show'])->name('cart.show');
Route::post('cart/items', [CartItemController::class, 'store'])->name('cart.items.store');
Route::patch('cart/items/{product:id}', [CartItemController::class, 'update'])->name('cart.items.update');
Route::delete('cart/items/{product:id}', [CartItemController::class, 'destroy'])->name('cart.items.destroy');

require __DIR__.'/settings.php';
