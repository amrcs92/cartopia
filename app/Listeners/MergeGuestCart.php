<?php

namespace App\Listeners;

use App\Cart\Cart;
use App\Cart\SessionCartStore;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Events\Login;

class MergeGuestCart
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $guest = new SessionCartStore(session()->driver());
        $lines = $guest->lines();

        if ($lines === []) {
            return;
        }

        $cart = Cart::forUser($event->user);
        $products = Product::query()->whereKey(array_keys($lines))->get()->keyBy('id');

        foreach ($lines as $productId => $quantity) {
            $product = $products->get($productId);
            if ($product !== null) {
                $cart->add($product, $quantity);
            }
        }

        $guest->clear();
    }
}
