<?php

namespace App\Cart;

use App\Models\Cart as CartModel;
use App\Models\User;

class DatabaseCartStore implements CartStore
{
    public function __construct(private readonly User $user) {}

    /**
     * @return array<int, int>
     */
    public function lines(): array
    {
        $cart = CartModel::query()->where('user_id', $this->user->id)->first();

        if ($cart === null) {
            return [];
        }

        $lines = [];
        foreach ($cart->items()->orderBy('id')->get() as $item) {
            $lines[$item->product_id] = $item->quantity;
        }

        return $lines;
    }

    public function set(int $productId, int $quantity): void
    {
        $this->cart()->items()->updateOrCreate(
            ['product_id' => $productId],
            ['quantity' => $quantity],
        );
    }

    public function remove(int $productId): void
    {
        $cart = CartModel::query()->where('user_id', $this->user->id)->first();
        $cart?->items()->where('product_id', $productId)->delete();
    }

    public function clear(): void
    {
        $cart = CartModel::query()->where('user_id', $this->user->id)->first();
        $cart?->items()->delete();
    }

    private function cart(): CartModel
    {
        return CartModel::query()->firstOrCreate(['user_id' => $this->user->id]);
    }
}
