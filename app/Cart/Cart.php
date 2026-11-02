<?php

namespace App\Cart;

use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use InvalidArgumentException;

class Cart
{
    public const MAX_QUANTITY = 99;

    public function __construct(private readonly CartStore $store) {}

    public static function forUser(User $user): self
    {
        return new self(new DatabaseCartStore($user));
    }

    public function add(Product $product, int $quantity): void
    {
        $current = $this->store->lines()[$product->id] ?? 0;
        $this->store->set($product->id, min($current + $quantity, self::MAX_QUANTITY));
    }

    public function update(Product $product, int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        $this->store->set($product->id, min($quantity, self::MAX_QUANTITY));
    }

    public function remove(Product $product): void
    {
        $this->store->remove($product->id);
    }

    public function has(Product $product): bool
    {
        return array_key_exists($product->id, $this->store->lines());
    }

    public function clear(): void
    {
        $this->store->clear();
    }

    public function count(): int
    {
        return $this->summary()->totalQuantity();
    }

    public function summary(): CartSummary
    {
        $quantities = $this->store->lines();

        if ($quantities === []) {
            return new CartSummary([], Money::zero());
        }

        $products = Product::with('primaryImage')
            ->whereKey(array_keys($quantities))
            ->get()
            ->keyBy('id');

        $lines = [];
        $subtotal = Money::zero();

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);
            if ($product === null) {
                continue;
            }

            if (! $product->is_visible) {
                $lines[] = new CartLine($product, $quantity, $product->price, Money::zero(), available: false);

                continue;
            }

            $lineTotal = $product->price->multiply($quantity);
            $lines[] = new CartLine($product, $quantity, $product->price, $lineTotal);
            $subtotal = $subtotal->add($lineTotal);
        }

        return new CartSummary($lines, $subtotal);
    }
}
