<?php

namespace App\Cart;

interface CartStore
{
    /**
     * @return array<int, int> productId => quantity
     */
    public function lines(): array;

    public function set(int $productId, int $quantity): void;

    public function remove(int $productId): void;

    public function clear(): void;
}
