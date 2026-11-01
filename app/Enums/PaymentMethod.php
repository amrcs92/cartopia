<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Wallet = 'wallet';
    case Stripe = 'stripe';
    case PayPal = 'paypal';

    /**
     * @return list<self>
     */
    public static function hosted(): array
    {
        return [self::Stripe, self::PayPal];
    }

    public function label(): string
    {
        return match ($this) {
            self::Wallet => 'Wallet',
            self::Stripe => 'Stripe',
            self::PayPal => 'PayPal',
        };
    }

    public function isHosted(): bool
    {
        return $this !== self::Wallet;
    }
}
