<?php

namespace App\Cart;

use App\Exceptions\EmptyCart;
use App\Models\ShippingMethod;
use App\Support\Money;

/**
 * A basket priced from the database for an order: the lines, the subtotal,
 * the delivery fee and the total, plus the order item snapshot. Both ways to
 * check out price through here, so they can never disagree. Read the cart
 * only after locking the user row.
 */
final readonly class PricedBasket
{
    private function __construct(
        public CartSummary $summary,
        public Money $fee,
        public Money $total,
    ) {}

    /**
     * @throws EmptyCart
     */
    public static function of(Cart $cart, ShippingMethod $method): self
    {
        $summary = $cart->summary();

        if ($summary->isEmpty()) {
            throw new EmptyCart('Your basket is empty.');
        }

        return new self($summary, $method->fee, $summary->subtotal->add($method->fee));
    }

    public function subtotal(): Money
    {
        return $this->summary->subtotal;
    }

    /**
     * The order item rows: each line as priced now, so later price changes
     * or deleted products never touch the order.
     *
     * @return list<array{product_id: int, product_name: string, unit_price: Money, quantity: int, line_total: Money}>
     */
    public function itemAttributes(): array
    {
        return array_map(fn (CartLine $line): array => [
            'product_id' => $line->product->id,
            'product_name' => $line->product->name,
            'unit_price' => $line->unitPrice,
            'quantity' => $line->quantity,
            'line_total' => $line->lineTotal,
        ], $this->summary->lines);
    }
}
