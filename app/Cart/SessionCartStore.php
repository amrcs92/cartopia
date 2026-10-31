<?php

namespace App\Cart;

use Illuminate\Contracts\Session\Session;

class SessionCartStore implements CartStore
{
    private const KEY = 'cart';

    public function __construct(private readonly Session $session) {}

    /**
     * @return array<int, int>
     */
    public function lines(): array
    {
        $raw = $this->session->get(self::KEY, []);
        $lines = [];

        if (is_array($raw)) {
            foreach ($raw as $productId => $quantity) {
                $lines[(int) $productId] = (int) $quantity;
            }
        }

        return $lines;
    }

    public function set(int $productId, int $quantity): void
    {
        $lines = $this->lines();
        $lines[$productId] = $quantity;
        $this->session->put(self::KEY, $lines);
    }

    public function remove(int $productId): void
    {
        $lines = $this->lines();
        unset($lines[$productId]);
        $this->session->put(self::KEY, $lines);
    }

    public function clear(): void
    {
        $this->session->put(self::KEY, []);
    }
}
